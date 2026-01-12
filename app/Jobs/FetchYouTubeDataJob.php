<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Services\YouTubeService;
use App\Exceptions\YouTubeApiException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Background job to fetch YouTube data for artists with YouTube channel IDs.
 *
 * Processes artists in batches to optimize quota usage and implements
 * idempotent processing with comprehensive error handling.
 *
 * NOTE: This job does not perform staleness checks (e.g., checking if metrics
 * were updated in the last 24h). It is the responsibility of the caller
 * (e.g., YouTubeJobDispatchService or ArtistSearchService) to ensure that
 * this job is only dispatched for artists that actually need a refresh.
 */
class FetchYouTubeDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addHours(2);
    }

    /**
     * Create a new job instance.
     *
     * @param array<int> $artistIds Array of artist IDs to process
     * @param int $batchSize Maximum number of channels to process per API request
     */
    public function __construct(
        private array $artistIds,
        private int $batchSize = 50
    ) {}

    /**
     * Execute the job.
     */
    public function handle(YouTubeService $youTubeService): void
    {
        if (empty($this->artistIds)) {
            Log::info('FetchYouTubeDataJob: No artist IDs provided');
            return;
        }

        Log::info('FetchYouTubeDataJob: Starting job', [
            'artist_count' => count($this->artistIds),
            'batch_size' => $this->batchSize
        ]);

        // Check quota availability before processing
        if (!$youTubeService->checkQuotaAvailability()) {
            Log::warning('FetchYouTubeDataJob: YouTube quota exhausted, skipping job');
            return;
        }

        $processedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        try {
            // Fetch artists with YouTube channel IDs in batches
            $artists = Artist::whereIn('id', $this->artistIds)
                ->whereNotNull('youtube_channel_id')
                ->with('metrics')
                ->get();

            if ($artists->isEmpty()) {
                Log::info('FetchYouTubeDataJob: No artists with YouTube channel IDs found');
                return;
            }

            // Group artists by channel ID to handle duplicates (idempotent processing)
            $channelGroups = $artists->groupBy('youtube_channel_id');

            // Process in batches to optimize quota usage
            $channelIds = $channelGroups->keys()->toArray();
            $batches = array_chunk($channelIds, $this->batchSize);

            foreach ($batches as $batchIndex => $channelBatch) {
                try {
                    Log::debug('FetchYouTubeDataJob: Processing batch', [
                        'batch_index' => $batchIndex + 1,
                        'batch_size' => count($channelBatch),
                        'total_batches' => count($batches)
                    ]);

                    // Fetch channel metrics for the batch
                    $channelMetrics = $youTubeService->getMultipleChannelMetrics($channelBatch);

                    // Process each channel's results
                    foreach ($channelBatch as $channelId) {
                        $channelData = $channelMetrics[$channelId] ?? null;
                        $artistsForChannel = $channelGroups[$channelId];

                        foreach ($artistsForChannel as $artist) {
                            try {
                                $this->updateArtistMetrics($artist, $channelData);
                                $processedCount++;
                            } catch (\Exception $e) {
                                Log::error('FetchYouTubeDataJob: Failed to update artist metrics', [
                                    'artist_id' => $artist->id,
                                    'channel_id' => $channelId,
                                    'error' => $e->getMessage()
                                ]);
                                $errorCount++;
                            }
                        }
                    }

                    // Check quota after each batch
                    if (!$youTubeService->checkQuotaAvailability()) {
                        Log::warning('FetchYouTubeDataJob: Quota exhausted during processing', [
                            'processed_batches' => $batchIndex + 1,
                            'total_batches' => count($batches),
                            'processed_count' => $processedCount
                        ]);
                        break;
                    }

                } catch (YouTubeApiException $e) {
                    if ($e->isQuotaExhausted) {
                        Log::warning('FetchYouTubeDataJob: Quota exhausted, stopping processing', [
                            'processed_batches' => $batchIndex,
                            'total_batches' => count($batches),
                            'processed_count' => $processedCount
                        ]);
                        break;
                    } else {
                        Log::error('FetchYouTubeDataJob: API error in batch', [
                            'batch_index' => $batchIndex + 1,
                            'error' => $e->getMessage(),
                            'status_code' => $e->statusCode
                        ]);
                        $errorCount += count($channelBatch);
                        
                        // Continue with next batch for non-quota errors
                        continue;
                    }
                } catch (\Exception $e) {
                    Log::error('FetchYouTubeDataJob: Unexpected error in batch', [
                        'batch_index' => $batchIndex + 1,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errorCount += count($channelBatch);
                    
                    // Continue with next batch
                    continue;
                }
            }

        } catch (\Exception $e) {
            Log::error('FetchYouTubeDataJob: Fatal error during job execution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'artist_ids' => $this->artistIds
            ]);
            
            // Re-throw to trigger job retry
            throw $e;
        }

        Log::info('FetchYouTubeDataJob: Job completed', [
            'total_artists' => count($this->artistIds),
            'processed_count' => $processedCount,
            'error_count' => $errorCount,
            'skipped_count' => $skippedCount,
            'remaining_quota' => $youTubeService->getRemainingQuota()
        ]);
    }

    /**
     * Update artist metrics with YouTube data.
     *
     * @param Artist $artist
     * @param \App\DataTransferObjects\YouTubeChannelDTO|null $channelData
     */
    private function updateArtistMetrics(Artist $artist, $channelData): void
    {
        DB::transaction(function () use ($artist, $channelData) {
            // Ensure artist has metrics record
            if (!$artist->metrics) {
                $artist->metrics()->create([
                    'refreshed_at' => now(),
                ]);
                $artist->load('metrics');
            }

            $updateData = [
                'youtube_refreshed_at' => now(),
            ];

            if ($channelData) {
                $updateData['youtube_subscribers'] = $channelData->subscriberCount;
                
                Log::debug('FetchYouTubeDataJob: Updated artist YouTube metrics', [
                    'artist_id' => $artist->id,
                    'channel_id' => $artist->youtube_channel_id,
                    'subscribers' => $channelData->subscriberCount,
                    'video_count' => $channelData->videoCount
                ]);
            } else {
                // Channel not found or private - set to null but record the attempt
                $updateData['youtube_subscribers'] = null;
                
                Log::info('FetchYouTubeDataJob: Channel not found or private', [
                    'artist_id' => $artist->id,
                    'channel_id' => $artist->youtube_channel_id
                ]);
            }

            $artist->metrics->update($updateData);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $errorContext = [
            'artist_ids' => $this->artistIds,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'exception_type' => get_class($exception)
        ];

        if ($exception instanceof YouTubeApiException) {
            $errorContext['status_code'] = $exception->statusCode;
            $errorContext['quota_cost'] = $exception->quotaCost;
            $errorContext['is_quota_exhausted'] = $exception->isQuotaExhausted;

            if ($exception->isQuotaExhausted) {
                Log::warning('FetchYouTubeDataJob: Job failed due to quota exhaustion', $errorContext);
                
                // Schedule job for next day when quota resets
                $this->scheduleForQuotaReset();
                return;
            }
        }

        Log::error('FetchYouTubeDataJob: Job failed permanently', array_merge($errorContext, [
            'trace' => $exception->getTraceAsString()
        ]));

        // Continue processing other artists by dispatching smaller batches
        $this->dispatchFallbackJobs();
    }

    /**
     * Schedule job for when quota resets (next day).
     */
    private function scheduleForQuotaReset(): void
    {
        $nextDay = now()->addDay()->startOfDay();
        
        Log::info('FetchYouTubeDataJob: Scheduling job for quota reset', [
            'artist_ids' => $this->artistIds,
            'scheduled_for' => $nextDay->toISOString()
        ]);

        // Dispatch the same job for tomorrow
        static::dispatch($this->artistIds, $this->batchSize)
            ->delay($nextDay);
    }

    /**
     * Dispatch smaller batch jobs as fallback when main job fails.
     */
    private function dispatchFallbackJobs(): void
    {
        if (count($this->artistIds) <= 1) {
            // Can't split further, log and give up
            Log::error('FetchYouTubeDataJob: Cannot split job further, giving up', [
                'artist_ids' => $this->artistIds
            ]);
            return;
        }

        // Split into smaller batches and dispatch
        $smallerBatchSize = max(1, intval($this->batchSize / 2));
        $chunks = array_chunk($this->artistIds, $smallerBatchSize);

        Log::info('FetchYouTubeDataJob: Dispatching fallback jobs', [
            'original_batch_size' => count($this->artistIds),
            'new_batch_size' => $smallerBatchSize,
            'number_of_jobs' => count($chunks)
        ]);

        foreach ($chunks as $chunk) {
            static::dispatch($chunk, min($smallerBatchSize, 10))
                ->delay(now()->addMinutes(5)); // Small delay to avoid immediate retry
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Determine if the job should be retried based on the exception.
     */
    public function shouldRetry(\Throwable $exception): bool
    {
        // Don't retry on quota exhaustion - wait for daily reset
        if ($exception instanceof YouTubeApiException && $exception->isQuotaExhausted) {
            Log::info('FetchYouTubeDataJob: Not retrying due to quota exhaustion', [
                'artist_ids' => $this->artistIds,
                'exception' => $exception->getMessage()
            ]);
            return false;
        }

        // Don't retry on permanent API errors (4xx except 429)
        if ($exception instanceof YouTubeApiException) {
            $statusCode = $exception->statusCode;
            if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                Log::info('FetchYouTubeDataJob: Not retrying due to permanent API error', [
                    'status_code' => $statusCode,
                    'artist_ids' => $this->artistIds,
                    'exception' => $exception->getMessage()
                ]);
                return false;
            }
        }

        // Retry on network errors, 5xx errors, and rate limiting (429)
        return true;
    }
}
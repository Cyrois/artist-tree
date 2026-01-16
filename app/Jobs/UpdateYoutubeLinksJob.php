<?php

namespace App\Jobs;

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Enums\SocialPlatform;
use App\Exceptions\YouTubeApiException;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Services\VEVOChannelDetectionService;
use App\Services\YouTubeChannelRankingAlgorithm;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background job for processing artist's youtube links.
 *
 * This job grabs all the artist's YouTube links, finds the best channel
 * using the YouTubeChannelRankingAlgorithm (ignoring VEVO channels),
 * and promotes the best one as the main YouTube link.
 */
class UpdateYoutubeLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        $baseDelay = (int) config('artist-tree.vevo_detection.retry_delay', 60);
        // Exponential backoff: base, base*2, base*4
        return [$baseDelay, $baseDelay * 2, $baseDelay * 4];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Artist $artist,
    ) {
        $this->tries = (int) config('artist-tree.vevo_detection.max_retries', 3);
        $this->backoff = (int) config('artist-tree.vevo_detection.retry_delay', 60);
        $queue = config('artist-tree.vevo_detection.queue', 'default');
        $this->onQueue($queue);
    }

    /**
     * Execute the job.
     */
    public function handle(
        VEVOChannelDetectionService $vevoDetectionService,
        YouTubeChannelRankingAlgorithm $rankingAlgorithm,
        YouTubeService $youtubeService,
    ): void {
        Log::info('UpdateYoutubeLinksJob: Starting processing', [
            'artist_id' => $this->artist->id,
            'artist_name' => $this->artist->name,
            'youtube_channel_id' => $this->artist->youtube_channel_id,
        ]);

        try {
            // Step 0: Check if artist needs to be updated
            if (!$this->artist->needsToUpdateYoutubeChannel()) {
                Log::debug('UpdateYoutubeLinksJob: Artist does not need YouTube channel update', [
                    'artist_id' => $this->artist->id,
                ]);
                return;
            }

            // Step 1: Get all YouTube links for the artist
            $youtubeLinks = $this->artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->get();

            if ($youtubeLinks->isEmpty()) {
                Log::info('UpdateYoutubeLinksJob: No YouTube links found', [
                    'artist_id' => $this->artist->id,
                ]);
                return;
            }

            // Step 2: Fetch channel data for each link (filter out VEVO channels)
            $channels = [];
            $seenChannelIds = [];
            $channelIdToUrl = []; // Track original URLs for each channel

            foreach ($youtubeLinks as $link) {
                $channelId = $vevoDetectionService->extractChannelIdFromUrl($link->url);
                if (!$channelId) {
                    continue;
                }

                // Skip already processed channel IDs
                if (isset($seenChannelIds[$channelId])) {
                    continue;
                }
                $seenChannelIds[$channelId] = true;
                $channelIdToUrl[$channelId] = $link->url; // Store original URL

                $channelData = $youtubeService->getChannelMetrics($channelId);
                if (!$channelData) {
                    continue;
                }

                // Skip VEVO channels
                if ($vevoDetectionService->isVEVOChannel($channelData)) {
                    Log::debug('UpdateYoutubeLinksJob: Skipping VEVO channel', [
                        'artist_id' => $this->artist->id,
                        'channel_id' => $channelId,
                        'channel_title' => $channelData->title,
                    ]);
                    continue;
                }

                $channels[] = $channelData;
            }

            if (empty($channels)) {
                Log::info('UpdateYoutubeLinksJob: No valid non-VEVO channels found', [
                    'artist_id' => $this->artist->id,
                ]);
                return;
            }

            // Step 3: Rank and select the best channel
            $bestChannel = $rankingAlgorithm->selectBestChannel($channels);
            if ($bestChannel === null) {
                Log::info('UpdateYoutubeLinksJob: No channels meet minimum requirements', [
                    'artist_id' => $this->artist->id,
                    'channel_count' => count($channels),
                ]);
                return;
            }

            // Step 4: Promote the best channel
            $originalUrl = $channelIdToUrl[$bestChannel->channelId] ?? null;
            $this->promoteChannel($bestChannel, $originalUrl);

            Log::info('UpdateYoutubeLinksJob: Successfully promoted best YouTube channel', [
                'artist_id' => $this->artist->id,
                'channel_id' => $bestChannel->channelId,
                'channel_title' => $bestChannel->title,
                'subscriber_count' => $bestChannel->subscriberCount,
            ]);

        } catch (YouTubeApiException $e) {
            $this->handleApiException($e);
        } catch (\Exception $e) {
            Log::error('UpdateYoutubeLinksJob: Unexpected error', [
                'artist_id' => $this->artist->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Promote the channel to be the artist's official YouTube channel.
     * Updates the artist record and the YouTube ArtistLink.
     *
     * @param YouTubeChannelDTO $channel The channel to promote
     * @param string|null $originalUrl The original URL of the link (for finding the correct link to update)
     */
    private function promoteChannel(YouTubeChannelDTO $channel, ?string $originalUrl = null): void
    {
        DB::transaction(function () use ($channel, $originalUrl) {
            // Update artist's youtube_channel_id
            $this->artist->update([
                'youtube_channel_id' => $channel->channelId,
            ]);

            // Find the existing link by original URL
            $youtubeLink = null;
            if ($originalUrl) {
                $youtubeLink = $this->artist->links()
                    ->where('platform', SocialPlatform::YouTube)
                    ->where('url', $originalUrl)
                    ->first();
            }

            if ($youtubeLink) {
                // Update status only - keep original URL unchanged
                $youtubeLink->update([
                    'review_status' => ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
                    'vevo_checked_at' => now(),
                ]);
            } else {
                // Create new link with canonical channel URL
                $this->artist->links()->create([
                    'platform' => SocialPlatform::YouTube,
                    'url' => "https://www.youtube.com/channel/{$channel->channelId}",
                    'review_status' => ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
                    'vevo_checked_at' => now(),
                ]);
            }
        });

        // Dispatch job to fetch YouTube metrics for the newly promoted channel
        FetchYouTubeDataJob::dispatch([$this->artist->id]);
    }

    /**
     * Handle YouTube API exceptions with appropriate retry logic.
     */
    private function handleApiException(YouTubeApiException $e): void
    {
        Log::warning('UpdateYoutubeLinksJob: YouTube API error', [
            'artist_id' => $this->artist->id,
            'error' => $e->getMessage(),
            'status_code' => $e->statusCode,
            'is_quota_exhausted' => $e->isQuotaExhausted,
        ]);

        if ($e->isQuotaExhausted) {
            // Release job to retry after quota reset (1 hour)
            $this->release(3600);
            return;
        }

        // For other API errors, use standard retry with backoff
        if ($this->attempts() < $this->tries) {
            $delay = $this->backoff()[$this->attempts() - 1] ?? 240;
            $this->release($delay);
            return;
        }

        // Max retries exceeded, fail the job
        $this->fail($e);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateYoutubeLinksJob: Job failed permanently', [
            'artist_id' => $this->artist->id,
            'artist_name' => $this->artist->name,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'youtube-links',
            'artist:' . $this->artist->id,
        ];
    }
}

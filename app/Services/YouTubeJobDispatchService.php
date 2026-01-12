<?php

namespace App\Services;

use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for dispatching YouTube data fetching jobs with priority-based processing.
 *
 * Implements priority system:
 * - High priority: Artists with stale basic metrics (>24 hours)
 * - Medium priority: Artists with stale video analytics (>7 days)
 * - Low priority: Artists needing first-time video analytics
 */
class YouTubeJobDispatchService
{
    /**
     * Batch sizes for different priority levels.
     */
    private const HIGH_PRIORITY_BATCH_SIZE = 20;
    private const MEDIUM_PRIORITY_BATCH_SIZE = 15;
    private const LOW_PRIORITY_BATCH_SIZE = 10;

    public function __construct(
        private readonly YouTubeService $youtubeService
    ) {}

    /**
     * Dispatch YouTube jobs for artists with priority-based processing.
     *
     * @param array<int> $artistIds Array of artist IDs to process
     * @param bool $respectQuota Whether to check quota availability before dispatching
     * @return array Statistics about dispatched jobs
     */
    public function dispatchPriorityJobs(array $artistIds, bool $respectQuota = true): array
    {
        $stats = $this->initializeStats(count($artistIds));

        if (empty($artistIds)) {
            return $stats;
        }

        // Check quota availability if requested
        if ($respectQuota && !$this->youtubeService->checkQuotaAvailability()) {
            Log::warning('YouTubeJobDispatchService: Quota exhausted, skipping job dispatch', [
                'artist_count' => count($artistIds),
            ]);
            $stats['quota_exhausted'] = true;
            return $stats;
        }

        // Fetch artists with YouTube channel IDs and metrics
        $artists = Artist::whereIn('id', $artistIds)
            ->whereNotNull('youtube_channel_id')
            ->with('metrics')
            ->get();

        if ($artists->isEmpty()) {
            Log::info('YouTubeJobDispatchService: No artists with YouTube channel IDs found', [
                'requested_count' => count($artistIds),
            ]);
            $stats['total_artists'] = 0;
            return $stats;
        }

        $stats['total_artists'] = $artists->count();

        // Categorize artists by priority
        $priorityGroups = $this->categorizeArtistsByPriority($artists);

        // Dispatch high priority jobs first
        if ($priorityGroups['high']->isNotEmpty()) {
            $jobCount = $this->dispatchJobsForPriority(
                $priorityGroups['high'],
                'high',
                self::HIGH_PRIORITY_BATCH_SIZE
            );
            $stats['high_priority'] = $jobCount;
            $stats['total_jobs'] += $jobCount;
        }

        // Dispatch medium priority jobs
        if ($priorityGroups['medium']->isNotEmpty()) {
            $jobCount = $this->dispatchJobsForPriority(
                $priorityGroups['medium'],
                'medium',
                self::MEDIUM_PRIORITY_BATCH_SIZE
            );
            $stats['medium_priority'] = $jobCount;
            $stats['total_jobs'] += $jobCount;
        }

        // Dispatch low priority jobs
        if ($priorityGroups['low']->isNotEmpty()) {
            $jobCount = $this->dispatchJobsForPriority(
                $priorityGroups['low'],
                'low',
                self::LOW_PRIORITY_BATCH_SIZE
            );
            $stats['low_priority'] = $jobCount;
            $stats['total_jobs'] += $jobCount;
        }

        Log::info('YouTubeJobDispatchService: Priority jobs dispatched', $stats);

        return $stats;
    }

    /**
     * Initialize a standard statistics response array.
     *
     * @param int $totalArtists
     * @return array
     */
    private function initializeStats(int $totalArtists = 0): array
    {
        return [
            'high_priority' => 0,
            'medium_priority' => 0,
            'low_priority' => 0,
            'total_jobs' => 0,
            'total_artists' => $totalArtists,
            'quota_exhausted' => false,
        ];
    }

    /**
     * Categorize artists by priority based on their YouTube data staleness.
     *
     * @param Collection<Artist> $artists
     * @return array<string, Collection<Artist>>
     */
    private function categorizeArtistsByPriority(Collection $artists): array
    {
        $high = collect();
        $medium = collect();
        $low = collect();

        foreach ($artists as $artist) {
            // Artists without metrics get high priority (first-time fetch)
            if (!$artist->metrics) {
                $high->push($artist);
                continue;
            }

            $metrics = $artist->metrics;

            // High priority: Basic metrics are stale (>24 hours)
            if ($metrics->isYouTubeStale()) {
                $high->push($artist);
            }
            // Low priority: First-time analytics fetch (has basic data but no analytics)
            elseif (!$metrics->hasYouTubeAnalytics() && $metrics->hasYouTubeData()) {
                $low->push($artist);
            }
            // Medium priority: Analytics are stale (>7 days) but basic metrics are fresh
            elseif ($metrics->isYouTubeAnalyticsStale()) {
                $medium->push($artist);
            }
            // Skip artists with fresh data
        }

        return [
            'high' => $high,
            'medium' => $medium,
            'low' => $low,
        ];
    }

    /**
     * Dispatch jobs for a specific priority group.
     *
     * @param Collection<Artist> $artists
     * @param string $priority
     * @param int $batchSize
     * @return int Number of jobs dispatched
     */
    private function dispatchJobsForPriority(Collection $artists, string $priority, int $batchSize): int
    {
        $artistIds = $artists->pluck('id')->toArray();
        $batches = array_chunk($artistIds, $batchSize);
        $jobCount = 0;

        foreach ($batches as $batch) {
            // Add delay based on priority to manage system load
            $delay = $this->getDelayForPriority($priority, $jobCount);
            
            FetchYouTubeDataJob::dispatch($batch, $batchSize)
                ->delay($delay);
            
            $jobCount++;
        }

        Log::debug('YouTubeJobDispatchService: Dispatched priority batch', [
            'priority' => $priority,
            'artist_count' => count($artistIds),
            'job_count' => $jobCount,
            'batch_size' => $batchSize,
        ]);

        return $jobCount;
    }

    /**
     * Get delay for job based on priority and job index.
     *
     * @param string $priority
     * @param int $jobIndex
     * @return \DateTimeInterface|int
     */
    private function getDelayForPriority(string $priority, int $jobIndex)
    {
        return match ($priority) {
            'high' => now()->addSeconds($jobIndex * 30), // 30 second intervals
            'medium' => now()->addMinutes($jobIndex * 2), // 2 minute intervals
            'low' => now()->addMinutes(5 + ($jobIndex * 5)), // 5+ minute intervals
            default => now(),
        };
    }

    /**
     * Dispatch YouTube jobs for artists that need basic metrics refresh.
     *
     * @param array<int> $artistIds
     * @return int Number of jobs dispatched
     */
    public function dispatchBasicMetricsJobs(array $artistIds): int
    {
        if (empty($artistIds)) {
            return 0;
        }

        $artists = Artist::whereIn('id', $artistIds)
            ->whereNotNull('youtube_channel_id')
            ->with('metrics')
            ->get()
            ->filter(function ($artist) {
                return !$artist->metrics || $artist->metrics->isYouTubeStale();
            });

        if ($artists->isEmpty()) {
            return 0;
        }

        $batches = $artists->pluck('id')->chunk(self::HIGH_PRIORITY_BATCH_SIZE);
        $jobCount = 0;

        foreach ($batches as $batch) {
            FetchYouTubeDataJob::dispatch($batch->toArray(), self::HIGH_PRIORITY_BATCH_SIZE);
            $jobCount++;
        }

        Log::info('YouTubeJobDispatchService: Dispatched basic metrics jobs', [
            'artist_count' => $artists->count(),
            'job_count' => $jobCount,
        ]);

        return $jobCount;
    }

    /**
     * Dispatch YouTube jobs for artists that need video analytics refresh.
     *
     * @param array<int> $artistIds
     * @return int Number of jobs dispatched
     */
    public function dispatchAnalyticsJobs(array $artistIds): int
    {
        if (empty($artistIds)) {
            return 0;
        }

        $artists = Artist::whereIn('id', $artistIds)
            ->whereNotNull('youtube_channel_id')
            ->with('metrics')
            ->get()
            ->filter(function ($artist) {
                return $artist->metrics && $artist->metrics->isYouTubeAnalyticsStale();
            });

        if ($artists->isEmpty()) {
            return 0;
        }

        $batches = $artists->pluck('id')->chunk(self::MEDIUM_PRIORITY_BATCH_SIZE);
        $jobCount = 0;

        foreach ($batches as $batch) {
            FetchYouTubeDataJob::dispatch($batch->toArray(), self::MEDIUM_PRIORITY_BATCH_SIZE)
                ->delay(now()->addMinutes($jobCount * 2)); // Stagger analytics jobs
            $jobCount++;
        }

        Log::info('YouTubeJobDispatchService: Dispatched analytics jobs', [
            'artist_count' => $artists->count(),
            'job_count' => $jobCount,
        ]);

        return $jobCount;
    }
}
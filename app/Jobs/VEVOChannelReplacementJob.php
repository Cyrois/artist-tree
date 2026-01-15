<?php

namespace App\Jobs;

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Enums\SocialPlatform;
use App\Exceptions\YouTubeApiException;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Services\VEVOChannelDetectionService;
use App\Services\YouTubeChannelRankingAlgorithm;
use App\Services\YouTubeChannelSearchService;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Background job for processing VEVO channel replacement for an artist.
 *
 * This job detects if an artist has a VEVO channel, searches for alternative
 * channels, ranks them, and replaces the VEVO channel with the best alternative.
 */
class VEVOChannelReplacementJob implements ShouldQueue
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
        VEVOChannelDetectionService $detectionService,
        YouTubeChannelSearchService $searchService,
        YouTubeChannelRankingAlgorithm $rankingAlgorithm,
        YouTubeService $youtubeService,
    ): void {
        Log::info('VEVOChannelReplacementJob: Starting processing', [
            'artist_id' => $this->artist->id,
            'artist_name' => $this->artist->name,
            'youtube_channel_id' => $this->artist->youtube_channel_id,
        ]);

        try {
            // Step 1: Check if artist should be processed
            if (!$detectionService->shouldCheckArtist($this->artist)) {
                Log::debug('VEVOChannelReplacementJob: Artist already checked recently', [
                    'artist_id' => $this->artist->id,
                ]);
                return;
            }

            // Step 2: If artist has no youtube_channel_id, try to populate from links or discover
            if (!$this->artist->youtube_channel_id) {
                $this->populateChannelFromLinksOrDiscover(
                    $youtubeService,
                    $searchService,
                    $rankingAlgorithm,
                    $detectionService
                );
                return;
            }

            // Step 3: Get current channel data for VEVO detection
            $currentChannel = $youtubeService->getChannelMetrics($this->artist->youtube_channel_id);
            if ($currentChannel === null) {
                Log::warning('VEVOChannelReplacementJob: Could not fetch current channel data', [
                    'artist_id' => $this->artist->id,
                    'youtube_channel_id' => $this->artist->youtube_channel_id,
                ]);
                return;
            }

            // Step 4: Detect if current channel is VEVO
            $isVEVO = $detectionService->detectVEVOChannelForArtist($this->artist, $currentChannel);
            if (!$isVEVO) {
                Log::debug('VEVOChannelReplacementJob: Current channel is not VEVO', [
                    'artist_id' => $this->artist->id,
                    'channel_title' => $currentChannel->title,
                ]);
                return;
            }

            Log::info('VEVOChannelReplacementJob: VEVO channel detected, searching for replacement', [
                'artist_id' => $this->artist->id,
                'vevo_channel_id' => $this->artist->youtube_channel_id,
                'vevo_channel_title' => $currentChannel->title,
            ]);

            // Step 5: Search for alternative channels
            $candidates = $searchService->searchChannelsForArtist($this->artist);
            if (empty($candidates)) {
                Log::info('VEVOChannelReplacementJob: No alternative channels found', [
                    'artist_id' => $this->artist->id,
                ]);
                return;
            }

            // Step 6: Rank and select best channel
            $bestChannel = $rankingAlgorithm->selectBestChannel($candidates);
            if ($bestChannel === null) {
                Log::info('VEVOChannelReplacementJob: No channels meet minimum requirements', [
                    'artist_id' => $this->artist->id,
                    'candidate_count' => count($candidates),
                ]);
                return;
            }

            // Step 7: Validate replacement
            if (!$rankingAlgorithm->isValidReplacement($bestChannel, $currentChannel)) {
                Log::info('VEVOChannelReplacementJob: Replacement channel does not meet quality requirements', [
                    'artist_id' => $this->artist->id,
                    'replacement_channel_id' => $bestChannel->channelId,
                    'replacement_subscribers' => $bestChannel->subscriberCount,
                ]);
                return;
            }

            // Step 8: Perform replacement
            $this->promoteChannel($bestChannel);

            Log::info('VEVOChannelReplacementJob: Successfully replaced VEVO channel', [
                'artist_id' => $this->artist->id,
                'old_channel_id' => $currentChannel->channelId,
                'new_channel_id' => $bestChannel->channelId,
                'new_channel_title' => $bestChannel->title,
                'new_subscriber_count' => $bestChannel->subscriberCount,
            ]);

        } catch (YouTubeApiException $e) {
            $this->handleApiException($e);
        } catch (\Exception $e) {
            Log::error('VEVOChannelReplacementJob: Unexpected error', [
                'artist_id' => $this->artist->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Populate channel from existing links (with ranking) or discover via search.
     * If the artist has an approved YouTube link, we use it regardless of ranking/thresholds
     * provided it exists on YouTube.
     * If the artist has no approved YouTube link, we search for the best channel via ranking.
     */
    private function populateChannelFromLinksOrDiscover(
        YouTubeService $youtubeService,
        YouTubeChannelSearchService $searchService,
        YouTubeChannelRankingAlgorithm $rankingAlgorithm,
        VEVOChannelDetectionService $detectionService,
    ): void {
        // 1. Check for an approved YouTube link first
        $approvedLink = $this->artist->links()
            ->where('platform', SocialPlatform::YouTube)
            ->where('review_status', ArtistLink::REVIEW_STATUS_APPROVED)
            ->first();

        if ($approvedLink) {
            $channelId = $detectionService->extractChannelIdFromUrl($approvedLink->url);
            if ($channelId) {
                $channelData = $youtubeService->getChannelMetrics($channelId);
                // If we have an approved link, we use it regardless of ranking/thresholds
                // provided it exists on YouTube
                if ($channelData) {
                    $this->promoteChannel($channelData);
                    $detectionService->markArtistAsChecked($this->artist, $channelData->channelId);
                    
                    Log::info('VEVOChannelReplacementJob: maintaining approved YouTube channel', [
                        'artist_id' => $this->artist->id,
                        'channel_id' => $channelData->channelId,
                        'channel_title' => $channelData->title,
                    ]);
                    return;
                }
            }
        }

        // 2. Try to get channels from unverified YouTube links
        $channelsFromLinks = $this->getChannelsFromLinks($youtubeService, $detectionService);
        
        if (!empty($channelsFromLinks)) {
            Log::info('VEVOChannelReplacementJob: Found channels from existing unverified links', [
                'artist_id' => $this->artist->id,
                'channel_count' => count($channelsFromLinks),
            ]);
            
            // Use ranking algorithm to select the best channel
            $bestChannel = $rankingAlgorithm->selectBestChannel($channelsFromLinks);
            
            if ($bestChannel !== null) {
                $this->promoteChannel($bestChannel);
                $detectionService->markArtistAsChecked($this->artist, $bestChannel->channelId);
                
                Log::info('VEVOChannelReplacementJob: Populated youtube_channel_id from existing links', [
                    'artist_id' => $this->artist->id,
                    'channel_id' => $bestChannel->channelId,
                    'channel_title' => $bestChannel->title,
                    'subscriber_count' => $bestChannel->subscriberCount,
                ]);
                return;
            }
        }
        
        // 3. No valid channels from links, try to discover via search
        $this->discoverAndPopulateChannel($searchService, $rankingAlgorithm, $detectionService);
    }

    /**
     * Get channel data for unverified YouTube links.
     *
     * @return array<YouTubeChannelDTO>
     */
    private function getChannelsFromLinks(
        YouTubeService $youtubeService,
        VEVOChannelDetectionService $detectionService,
    ): array {
        $channels = [];
        $seenChannelIds = [];
        
        $unverifiedLinks = $detectionService->getUnverifiedYouTubeLinks($this->artist);
        
        foreach ($unverifiedLinks as $link) {
            $channelId = $detectionService->extractChannelIdFromUrl($link->url);
            
            // Skip if no channel ID or already processed
            if (!$channelId || isset($seenChannelIds[$channelId])) {
                continue;
            }
            
            $seenChannelIds[$channelId] = true;
            
            $channelData = $youtubeService->getChannelMetrics($channelId);
            if ($channelData) {
                $channels[] = $channelData;
            }
        }
        
        return $channels;
    }

    /**
     * Discover and populate a YouTube channel for an artist without one.
     */
    private function discoverAndPopulateChannel(
        YouTubeChannelSearchService $searchService,
        YouTubeChannelRankingAlgorithm $rankingAlgorithm,
        VEVOChannelDetectionService $detectionService,
    ): void {
        Log::info('VEVOChannelReplacementJob: No channel ID, searching for channel', [
            'artist_id' => $this->artist->id,
            'artist_name' => $this->artist->name,
        ]);

        // Search for channels for this artist
        $candidates = $searchService->searchChannelsForArtist($this->artist);
        if (empty($candidates)) {
            Log::info('VEVOChannelReplacementJob: No channels found for artist', [
                'artist_id' => $this->artist->id,
            ]);
            $detectionService->markArtistAsChecked($this->artist);
            return;
        }

        // Select the best channel
        $bestChannel = $rankingAlgorithm->selectBestChannel($candidates);
        if ($bestChannel === null) {
            Log::info('VEVOChannelReplacementJob: No channels meet minimum requirements', [
                'artist_id' => $this->artist->id,
                'candidate_count' => count($candidates),
            ]);
            $detectionService->markArtistAsChecked($this->artist);
            return;
        }

        // Populate the channel
        $this->promoteChannel($bestChannel);
        $detectionService->markArtistAsChecked($this->artist, $bestChannel->channelId);

        Log::info('VEVOChannelReplacementJob: Successfully populated YouTube channel', [
            'artist_id' => $this->artist->id,
            'channel_id' => $bestChannel->channelId,
            'channel_title' => $bestChannel->title,
            'subscriber_count' => $bestChannel->subscriberCount,
        ]);
    }

    /**
     * Promote the channel to be the artist's official YouTube channel.
     * Updates the artist record and the YouTube ArtistLink.
     */
    private function promoteChannel(YouTubeChannelDTO $channel): void
    {
        DB::transaction(function () use ($channel) {
            // Update artist's youtube_channel_id
            $this->artist->update([
                'youtube_channel_id' => $channel->channelId,
            ]);

            // Update or create YouTube link with pending_approval status
            $youtubeLink = $this->artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->first();

            if ($youtubeLink) {
                $youtubeLink->update([
                    'url' => "https://www.youtube.com/channel/{$channel->channelId}",
                    'review_status' => ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
                    'vevo_checked_at' => now(),
                ]);
            } else {
                $this->artist->links()->create([
                    'platform' => SocialPlatform::YouTube,
                    'url' => "https://www.youtube.com/channel/{$channel->channelId}",
                    'review_status' => ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
                    'vevo_checked_at' => now(),
                ]);
            }
        });
    }

    /**
     * Handle YouTube API exceptions with appropriate retry logic.
     */
    private function handleApiException(YouTubeApiException $e): void
    {
        Log::warning('VEVOChannelReplacementJob: YouTube API error', [
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
        Log::error('VEVOChannelReplacementJob: Job failed permanently', [
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
            'vevo-detection',
            'artist:' . $this->artist->id,
        ];
    }
}

<?php

namespace App\Services;

use App\DataTransferObjects\YouTubeChannelDTO;
use Illuminate\Support\Facades\Log;

/**
 * Algorithm for ranking and selecting the best YouTube channel for an artist.
 *
 * This service ranks candidate channels based on subscriber count, verification
 * status, and recent activity to select the best replacement for a VEVO channel.
 */
class YouTubeChannelRankingAlgorithm
{

    private int $minimumSubscriberThreshold;
    private float $recentActivityBonus;
    private float $officialBonus;

    public function __construct()
    {
        $this->minimumSubscriberThreshold = (int) config('artist-tree.youtube.minimum_subscribers', 1000);
        $verifiedPercent = (int) config('artist-tree.youtube.verified_bonus_percent', 20);
        $activityPercent = (int) config('artist-tree.youtube.activity_bonus_percent', 10);
        $officialPercent = (int) config('artist-tree.youtube.official_bonus_percent', 15);
        
        $this->recentActivityBonus = 1 + ($activityPercent / 100);
        $this->officialBonus = 1 + ($officialPercent / 100);
    }

    /**
     * Rank channels by calculated score.
     *
     * @param array<YouTubeChannelDTO> $channels Array of channels to rank
     * @return array<YouTubeChannelDTO> Sorted array with highest scores first
     */
    public function rankChannels(array $channels): array
    {
        // Filter out channels below minimum threshold
        $validChannels = array_filter(
            $channels,
            fn(YouTubeChannelDTO $channel) => $channel->meetsMinimumSubscriberThreshold($this->minimumSubscriberThreshold)
        );

        // Sort by calculated score (descending)
        usort($validChannels, function (YouTubeChannelDTO $a, YouTubeChannelDTO $b) {
            $scoreA = $this->calculateChannelScore($a);
            $scoreB = $this->calculateChannelScore($b);


            return $scoreB <=> $scoreA;
        });

        return array_values($validChannels);
    }

    /**
     * Calculate a score for a channel based on various factors.
     *
     * @param YouTubeChannelDTO $channel The channel to score
     * @return float The calculated score
     */
    public function calculateChannelScore(YouTubeChannelDTO $channel): float
    {
        // Channels below minimum threshold get zero score
        if (!$channel->meetsMinimumSubscriberThreshold($this->minimumSubscriberThreshold)) {
            return 0.0;
        }

        // Base score from subscriber count
        $score = (float) $channel->subscriberCount;


        // Bonus for recent activity
        if ($channel->hasActiveContent()) {
            $score *= $this->recentActivityBonus;
        }

        // Bonus for "official" in channel name
        if ($this->hasOfficialInName($channel)) {
            $score *= $this->officialBonus;
        }

        return $score;
    }

    /**
     * Check if channel name contains "official" (case-insensitive).
     *
     * @param YouTubeChannelDTO $channel The channel to check
     * @return bool True if channel name contains "official"
     */
    public function hasOfficialInName(YouTubeChannelDTO $channel): bool
    {
        if (empty($channel->title)) {
            return false;
        }

        return stripos($channel->title, 'official') !== false;
    }

    /**
     * Select the best channel from a list of candidates.
     *
     * @param array<YouTubeChannelDTO> $channels Array of candidate channels
     * @return YouTubeChannelDTO|null The best channel or null if none qualify
     */
    public function selectBestChannel(array $channels): ?YouTubeChannelDTO
    {
        if (empty($channels)) {
            return null;
        }

        $ranked = $this->rankChannels($channels);

        if (empty($ranked)) {
            Log::debug('YouTubeChannelRankingAlgorithm: No channels meet minimum threshold', [
                'candidate_count' => count($channels),
                'threshold' => $this->minimumSubscriberThreshold,
            ]);
            return null;
        }

        $best = $ranked[0];

        Log::debug('YouTubeChannelRankingAlgorithm: Selected best channel', [
            'channel_id' => $best->channelId,
            'title' => $best->title,
            'subscriber_count' => $best->subscriberCount,
            'score' => $this->calculateChannelScore($best),
        ]);

        return $best;
    }



    /**
     * Validate that a replacement channel is significantly better than the original.
     *
     * @param YouTubeChannelDTO $replacement The proposed replacement channel
     * @param YouTubeChannelDTO|null $original The original channel (if available)
     * @return bool True if replacement is valid
     */
    public function isValidReplacement(YouTubeChannelDTO $replacement, ?YouTubeChannelDTO $original = null): bool
    {
        // Must meet minimum threshold
        if (!$replacement->meetsMinimumSubscriberThreshold($this->minimumSubscriberThreshold)) {
            return false;
        }

        // If no original to compare, just check threshold
        if ($original === null) {
            return true;
        }

        // Replacement should have more subscribers than original VEVO channel
        // (VEVO channels typically have high subscriber counts but no content)
        // We accept replacement if it has at least 10% of original's subscribers
        // and has actual content (videos)
        if ($replacement->videoCount > 0 && $replacement->subscriberCount >= $original->subscriberCount * 0.1) {
            return true;
        }

        // Or if replacement has significantly more subscribers
        return $replacement->subscriberCount > $original->subscriberCount;
    }

    /**
     * Get the minimum subscriber threshold.
     *
     * @return int The minimum subscriber count
     */
    public function getMinimumSubscriberThreshold(): int
    {
        return $this->minimumSubscriberThreshold;
    }
}

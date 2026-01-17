<?php

namespace App\Services;

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Enums\SocialPlatform;
use App\Models\Artist;
use App\Models\ArtistLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for detecting VEVO YouTube channels.
 *
 * VEVO channels are typically redirect channels with no videos that point
 * to the artist's main YouTube channel. This service identifies such channels
 * so they can be replaced with the artist's primary channel.
 */
class VEVOChannelDetectionService
{
    /**
     * Known VEVO-related patterns in channel descriptions.
     */
    private const VEVO_DESCRIPTION_PATTERNS = [
        'vevo',
        'official music video',
        'redirects to',
        'visit the official channel',
    ];

    private int $cacheTtl;
    private int $recheckDays;
    private bool $enabled;

    public function __construct()
    {
        $this->cacheTtl = (int) config('artist-tree.youtube.cache_ttl', 604800);
        $this->recheckDays = (int) config('artist-tree.youtube.vevo_recheck_days', 7);
        $this->enabled = (bool) config('artist-tree.youtube.enabled', true);
    }

    /**
     * Check if a YouTube channel is a VEVO channel.
     *
     * @param YouTubeChannelDTO $channel The channel to check
     * @return bool True if the channel is identified as VEVO
     */
    public function isVEVOChannel(YouTubeChannelDTO $channel): bool
    {
        // Check 1: Channel name contains "VEVO" (case-insensitive)
        if ($this->containsVEVOInName($channel->title)) {
            return true;
        }

        // Check 2: Channel has zero videos (VEVO channels typically have no content)
        if ($channel->videoCount === 0) {
            return true;
        }

        // Check 3: Channel description mentions VEVO or redirection
        if ($this->containsVEVOPatterns($channel->description)) {
            return true;
        }

        return false;
    }

    /**
     * Check if an artist should be checked for VEVO channel or channel discovery.
     *
     * @param Artist $artist The artist to check
     * @return bool True if the artist should be checked
     */
    public function shouldCheckArtist(Artist $artist): bool
    {
        // Check if VEVO detection is enabled
        if (!$this->enabled) {
            return false;
        }

        // Check cache first
        $cacheKey = "vevo_detection:{$artist->id}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        // If artist has no youtube_channel_id, they need channel discovery
        // Check if we've recently tried to discover a channel for them
        if (!$artist->youtube_channel_id) {
            $youtubeLinks = $this->getUnverifiedYouTubeLinks($artist);
            
            // If they have unverified links, check if any need processing
            if ($youtubeLinks->isNotEmpty()) {
                foreach ($youtubeLinks as $link) {
                    if ($link->needsVevoCheck()) {
                        return true;
                    }
                }
                return false; // All links recently checked
            }
            
            // No links and no channel ID - need to discover a channel
            // But only if we haven't tried recently (check any YouTube link's vevo_checked_at)
            $anyYoutubeLink = $artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->first();
            
            if ($anyYoutubeLink && $anyYoutubeLink->vevo_checked_at) {
                $daysSinceCheck = now()->diffInDays($anyYoutubeLink->vevo_checked_at);
                if ($daysSinceCheck < $this->recheckDays) {
                    return false;
                }
            }
            
            // No recent check - should try to discover channel
            return true;
        }

        // Artist has youtube_channel_id - check for VEVO replacement
        $youtubeLinks = $this->getUnverifiedYouTubeLinks($artist);
        
        if ($youtubeLinks->isEmpty()) {
            // Has youtube_channel_id but no link record - should check
            return true;
        }

        // Check if any link needs VEVO check (not recently checked)
        foreach ($youtubeLinks as $link) {
            if ($link->needsVevoCheck()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get unverified YouTube links for an artist.
     *
     * @param Artist $artist The artist
     * @return \Illuminate\Support\Collection<ArtistLink>
     */
    public function getUnverifiedYouTubeLinks(Artist $artist): \Illuminate\Support\Collection
    {
        return $artist->links()
            ->where('platform', SocialPlatform::YouTube)
            ->where('review_status', '!=', ArtistLink::REVIEW_STATUS_APPROVED)
            ->get();
    }

    /**
     * Extract YouTube channel ID from a YouTube URL.
     *
     * @param string $url The YouTube URL
     * @return string|null The channel ID or null if not found
     */
    public function extractChannelIdFromUrl(string $url): ?string
    {
        // Match /channel/UC... format
        if (preg_match('#youtube\.com/channel/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        // Match /@username format (would need API call to resolve, return null for now)
        // Match /c/customname format (would need API call to resolve, return null for now)
        // Match /user/username format (would need API call to resolve, return null for now)

        return null;
    }

    /**
     * Detect if an artist has a VEVO channel.
     *
     * @param Artist $artist The artist to check
     * @param YouTubeChannelDTO|null $channelData Optional pre-fetched channel data
     * @return bool True if the artist has a VEVO channel
     */
    public function detectVEVOChannelForArtist(Artist $artist, ?YouTubeChannelDTO $channelData = null): bool
    {
        if (!$this->shouldCheckArtist($artist)) {
            return false;
        }

        // If no channel data provided, we can't detect
        if (!$channelData) {
            Log::debug('VEVO detection: No channel data available', [
                'artist_id' => $artist->id,
                'channel_id' => $artist->youtube_channel_id,
            ]);
            return false;
        }

        $isVEVO = $this->isVEVOChannel($channelData);

        // Mark as checked regardless of result
        $this->markArtistAsChecked($artist, $channelData->channelId);

        // Cache the result
        $cacheKey = "vevo_detection:{$artist->id}";
        Cache::put($cacheKey, $isVEVO, $this->cacheTtl);

        if ($isVEVO) {
            Log::info('VEVO channel detected', [
                'artist_id' => $artist->id,
                'artist_name' => $artist->name,
                'channel_id' => $channelData->channelId,
                'channel_title' => $channelData->title,
                'video_count' => $channelData->videoCount,
            ]);
        }

        return $isVEVO;
    }

    /**
     * Mark an artist as checked for VEVO detection.
     *
     * @param Artist $artist The artist to mark
     * @param string|null $channelId The channel ID that was checked
     */
    public function markArtistAsChecked(Artist $artist, ?string $channelId = null): void
    {
        // Mark all unverified YouTube links as checked
        $youtubeLinks = $this->getUnverifiedYouTubeLinks($artist);
        
        foreach ($youtubeLinks as $link) {
            // If we have a channel ID, only mark the matching link
            if ($channelId) {
                $linkChannelId = $this->extractChannelIdFromUrl($link->url);
                if ($linkChannelId === $channelId || $linkChannelId === null) {
                    $link->markVevoChecked();
                }
            } else {
                $link->markVevoChecked();
            }
        }

        // If no links exist but artist has youtube_channel_id, create a link record
        if ($youtubeLinks->isEmpty() && ($channelId || $artist->youtube_channel_id)) {
            $this->createYouTubeLink($artist, $channelId ?? $artist->youtube_channel_id);
        }
    }

    /**
     * Get the known VEVO detection patterns.
     *
     * @return array{name_patterns: array, description_patterns: array}
     */
    public function getVEVODetectionPatterns(): array
    {
        return [
            'name_patterns' => ['vevo'],
            'description_patterns' => self::VEVO_DESCRIPTION_PATTERNS,
        ];
    }

    /**
     * Check if a channel name contains VEVO patterns.
     *
     * @param string|null $title The channel title to check
     * @return bool True if VEVO pattern found
     */
    private function containsVEVOInName(?string $title): bool
    {
        if (empty($title)) {
            return false;
        }

        return stripos($title, 'vevo') !== false;
    }

    /**
     * Check if a channel description contains VEVO-related patterns.
     *
     * @param string|null $description The channel description to check
     * @return bool True if VEVO pattern found
     */
    private function containsVEVOPatterns(?string $description): bool
    {
        if (empty($description)) {
            return false;
        }

        $lowerDescription = strtolower($description);

        foreach (self::VEVO_DESCRIPTION_PATTERNS as $pattern) {
            if (strpos($lowerDescription, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }


    /**
     * Create a YouTube link record for an artist.
     *
     * @param Artist $artist The artist
     * @param string $channelId The YouTube channel ID
     * @return ArtistLink The created link
     */
    private function createYouTubeLink(Artist $artist, string $channelId): ArtistLink
    {
        return $artist->links()->create([
            'platform' => SocialPlatform::YouTube,
            'url' => "https://www.youtube.com/channel/{$channelId}",
            'vevo_checked_at' => now(),
        ]);
    }
}

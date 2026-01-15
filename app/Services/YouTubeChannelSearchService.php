<?php

namespace App\Services;

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Exceptions\YouTubeApiException;
use App\Models\Artist;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for searching YouTube channels for artists.
 *
 * This service discovers alternative YouTube channels for artists
 * by searching using artist names and aliases, then filtering out
 * VEVO channels from the results.
 */
class YouTubeChannelSearchService
{
    /**
     * Cache TTL for search results (24 hours).
     */
    private const SEARCH_CACHE_TTL = 86400;

    /**
     * Maximum number of candidate channels to return.
     */
    private const MAX_CANDIDATES = 10;

    /**
     * YouTube API search quota cost per request.
     */
    private const SEARCH_QUOTA_COST = 100;

    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        private readonly VEVOChannelDetectionService $vevoDetectionService,
        private readonly YouTubeService $youtubeService,
    ) {
        $baseUrl = config('services.youtube.base_url');
        $apiKey = config('services.youtube.api_key');

        if (empty($baseUrl)) {
            throw new \RuntimeException('YouTube API base URL not configured');
        }

        if (empty($apiKey)) {
            throw new \RuntimeException('YouTube API key not configured');
        }

        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * Search for YouTube channels for an artist.
     *
     * @param Artist $artist The artist to search for
     * @return array<YouTubeChannelDTO> Array of candidate channels (excluding VEVO)
     * @throws YouTubeApiException
     */
    public function searchChannelsForArtist(Artist $artist): array
    {
        $cacheKey = "channel_search:{$artist->id}";

        return Cache::remember($cacheKey, self::SEARCH_CACHE_TTL, function () use ($artist) {
            $candidates = [];

            // Search using primary artist name
            $primaryResults = $this->searchByQuery($artist->name);
            $candidates = array_merge($candidates, $primaryResults);

            // Search using artist aliases
            foreach ($artist->aliases ?? [] as $alias) {
                $aliasName = is_string($alias) ? $alias : ($alias->name ?? null);
                if ($aliasName) {
                    $aliasResults = $this->searchByQuery($aliasName);
                    $candidates = array_merge($candidates, $aliasResults);
                }
            }

            // Filter out VEVO channels
            $candidates = $this->filterVEVOChannels($candidates);

            // Remove duplicates by channel ID
            $candidates = $this->removeDuplicates($candidates);

            // Limit to max candidates
            return array_slice($candidates, 0, self::MAX_CANDIDATES);
        });
    }

    /**
     * Search YouTube for channels by query string.
     *
     * @param string $query The search query
     * @return array<YouTubeChannelDTO> Array of channel DTOs
     * @throws YouTubeApiException
     */
    public function searchByQuery(string $query): array
    {
        if (empty(trim($query))) {
            return [];
        }

        // Check quota availability
        if (!$this->youtubeService->checkQuotaAvailability(self::SEARCH_QUOTA_COST)) {
            Log::warning('YouTube channel search skipped: quota unavailable', [
                'query' => $query,
            ]);
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->retry(3, function ($attempt) {
                    return 1000 * (2 ** ($attempt - 1));
                })
                ->get($this->baseUrl . '/search', [
                    'part' => 'snippet',
                    'type' => 'channel',
                    'q' => $query,
                    'maxResults' => 10,
                    'key' => $this->apiKey,
                ]);

            if (!$response->successful()) {
                $this->handleApiError($response, $query);
            }

            $data = $response->json();
            $items = $data['items'] ?? [];

            if (empty($items)) {
                return [];
            }

            // Extract channel IDs for detailed info
            $channelIds = array_map(
                fn($item) => $item['id']['channelId'] ?? null,
                $items
            );
            $channelIds = array_filter($channelIds);

            if (empty($channelIds)) {
                return [];
            }

            // Get detailed channel info including statistics
            return $this->getChannelDetails($channelIds, $items);

        } catch (YouTubeApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('YouTube channel search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Filter out VEVO channels from candidates.
     *
     * @param array<YouTubeChannelDTO> $channels Array of channels
     * @return array<YouTubeChannelDTO> Filtered array without VEVO channels
     */
    public function filterVEVOChannels(array $channels): array
    {
        return array_values(array_filter(
            $channels,
            fn(YouTubeChannelDTO $channel) => !$this->vevoDetectionService->isVEVOChannel($channel)
        ));
    }

    /**
     * Validate if a channel likely belongs to an artist.
     *
     * @param YouTubeChannelDTO $channel The channel to validate
     * @param Artist $artist The artist to match against
     * @return bool True if channel likely belongs to artist
     */
    public function validateChannelOwnership(YouTubeChannelDTO $channel, Artist $artist): bool
    {
        $channelTitle = strtolower($channel->title ?? '');
        $artistName = strtolower($artist->name);

        // Check if channel title contains artist name
        if (str_contains($channelTitle, $artistName)) {
            return true;
        }

        // Check against aliases
        foreach ($artist->aliases ?? [] as $alias) {
            $aliasName = is_string($alias) ? $alias : ($alias->name ?? null);
            if ($aliasName && str_contains($channelTitle, strtolower($aliasName))) {
                return true;
            }
        }

        // Check if artist name contains channel title (for short names)
        if (strlen($channelTitle) >= 3 && str_contains($artistName, $channelTitle)) {
            return true;
        }

        return false;
    }

    /**
     * Get detailed channel information for a list of channel IDs.
     *
     * @param array<string> $channelIds Array of channel IDs
     * @param array $searchItems Original search result items for snippet data
     * @return array<YouTubeChannelDTO> Array of channel DTOs with full details
     */
    private function getChannelDetails(array $channelIds, array $searchItems): array
    {
        // Build a map of channel ID to snippet data from search results
        $snippetMap = [];
        foreach ($searchItems as $item) {
            $channelId = $item['id']['channelId'] ?? null;
            if ($channelId) {
                $snippetMap[$channelId] = $item['snippet'] ?? [];
            }
        }

        // Get detailed channel statistics
        $channelMetrics = $this->youtubeService->getMultipleChannelMetrics($channelIds);

        $channels = [];
        foreach ($channelMetrics as $channelId => $dto) {
            if ($dto === null) {
                continue;
            }

            // Enrich DTO with snippet data from search results
            $snippet = $snippetMap[$channelId] ?? [];
            
            $dto->title = $snippet['title'] ?? $dto->title;
            $dto->description = $snippet['description'] ?? $dto->description;

            $channels[] = $dto;
        }

        return $channels;
    }

    /**
     * Remove duplicate channels by channel ID.
     *
     * @param array<YouTubeChannelDTO> $channels Array of channels
     * @return array<YouTubeChannelDTO> Array with duplicates removed
     */
    private function removeDuplicates(array $channels): array
    {
        $seen = [];
        $unique = [];

        foreach ($channels as $channel) {
            if (!isset($seen[$channel->channelId])) {
                $seen[$channel->channelId] = true;
                $unique[] = $channel;
            }
        }

        return $unique;
    }

    /**
     * Handle API error responses.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $query
     * @throws YouTubeApiException
     */
    private function handleApiError($response, string $query): void
    {
        $statusCode = $response->status();
        $body = $response->json();

        Log::error('YouTube channel search API error', [
            'query' => $query,
            'status_code' => $statusCode,
            'response_body' => $body,
        ]);

        // Handle quota exhaustion
        if ($statusCode === 403) {
            $errorReason = $body['error']['errors'][0]['reason'] ?? '';
            if (in_array($errorReason, ['quotaExceeded', 'dailyLimitExceeded'])) {
                throw YouTubeApiException::quotaExhausted(self::SEARCH_QUOTA_COST);
            }
        }

        // Handle rate limiting
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After', 60);
            throw YouTubeApiException::rateLimited($retryAfter);
        }

        throw YouTubeApiException::fromResponse($response, "YouTube channel search failed for query: {$query}");
    }

    /**
     * Clear the search cache for an artist.
     *
     * @param Artist $artist The artist
     */
    public function clearCacheForArtist(Artist $artist): void
    {
        $cacheKey = "channel_search:{$artist->id}";
        Cache::forget($cacheKey);
    }
}

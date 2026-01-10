# Design Document: YouTube Integration

## Overview

This document outlines the design for implementing YouTube API integration in the Artist-Tree platform. The integration will fetch channel metrics (subscriber counts and video counts) for artists who already have YouTube channel IDs stored in the database. The design follows the established patterns from the existing Spotify integration while addressing YouTube's specific API characteristics and quota limitations.

## Architecture

The YouTube integration follows a service-oriented architecture similar to the existing Spotify integration:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Controllers   │───▶│    Services      │───▶│  External APIs  │
│                 │    │                  │    │                 │
│ ArtistController│    │ YouTubeService   │    │ YouTube Data    │
│                 │    │                  │    │ API v3          │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌──────────────────┐
│   Background    │    │     Caching      │
│     Jobs        │    │                  │
│                 │    │ Redis/File Cache │
│FetchYouTubeData │    │                  │
│      Job        │    │                  │
└─────────────────┘    └──────────────────┘
```

## Components and Interfaces

### YouTubeService

The core service class responsible for all YouTube API interactions.

**Class:** `App\Services\YouTubeService`

**Key Methods:**
- `getChannelMetrics(string $channelId): ?YouTubeChannelDTO`
- `getMultipleChannelMetrics(array $channelIds): array`
- `getChannelVideos(string $channelId, int $limit = 15): array`
- `getVideoAnalytics(array $videoIds): array`
- `calculateVideoAnalytics(string $channelId): ?YouTubeVideoAnalyticsDTO`
- `checkQuotaAvailability(): bool`
- `getRemainingQuota(): int`

**Configuration:**
```php
// config/services.php
'youtube' => [
    'api_key' => env('YOUTUBE_API_KEY'),
    'quota_limit' => env('YOUTUBE_QUOTA_LIMIT', 10000),
    'cache_ttl' => env('YOUTUBE_CACHE_TTL', 86400), // 24 hours
]
```

### YouTubeChannelDTO

Data Transfer Object for YouTube channel information.

**Class:** `App\DataTransferObjects\YouTubeChannelDTO`

**Properties:**
- `string $channelId` - YouTube channel ID
- `int $subscriberCount` - Number of subscribers
- `int $videoCount` - Number of videos
- `bool $subscriberCountHidden` - Whether subscriber count is hidden
- `?YouTubeVideoAnalyticsDTO $videoAnalytics` - Average video performance metrics

### YouTubeVideoAnalyticsDTO

Data Transfer Object for aggregated video analytics.

**Class:** `App\DataTransferObjects\YouTubeVideoAnalyticsDTO`

**Properties:**
- `int $videosAnalyzed` - Number of videos included in analysis (up to 15)
- `float $averageViews` - Average view count across analyzed videos
- `float $averageLikes` - Average like count across analyzed videos
- `float $averageComments` - Average comment count across analyzed videos
- `float $averageShares` - Average share count across analyzed videos (if available)
- `array $videoIds` - Array of video IDs that were analyzed

**Factory Methods:**
```php
public static function fromYouTubeResponse(array $data): self
public static function calculateFromVideos(array $videos): self
```

### FetchYouTubeDataJob

Background job for asynchronous YouTube data fetching.

**Class:** `App\Jobs\FetchYouTubeDataJob`

**Responsibilities:**
- Process batches of artists with YouTube channel IDs
- Update artist_metrics table with fetched data
- Handle API errors and quota exhaustion
- Implement retry logic with exponential backoff

### YouTubeApiException

Custom exception for YouTube API errors.

**Class:** `App\Exceptions\YouTubeApiException`

**Properties:**
- `int $statusCode` - HTTP status code
- `int $quotaCost` - Quota units consumed
- `bool $isQuotaExhausted` - Whether quota limit was reached

## Data Models

### Database Schema Updates

The integration will require new fields in the artist_metrics table to store video analytics:

**artist_metrics table additions:**
```sql
-- Add new YouTube analytics columns after existing youtube_subscribers
ALTER TABLE artist_metrics ADD COLUMN youtube_refreshed_at TIMESTAMP NULL AFTER youtube_subscribers;
ALTER TABLE artist_metrics ADD COLUMN youtube_avg_views BIGINT NULL AFTER youtube_refreshed_at;
ALTER TABLE artist_metrics ADD COLUMN youtube_avg_likes BIGINT NULL AFTER youtube_avg_views;
ALTER TABLE artist_metrics ADD COLUMN youtube_avg_comments BIGINT NULL AFTER youtube_avg_likes;
ALTER TABLE artist_metrics ADD COLUMN youtube_videos_analyzed INTEGER NULL AFTER youtube_avg_comments;
ALTER TABLE artist_metrics ADD COLUMN youtube_analytics_refreshed_at TIMESTAMP NULL AFTER youtube_videos_analyzed;
```

**Existing fields:**
- `youtube_subscribers` (bigint, nullable) - Already exists
- `refreshed_at` (timestamp, nullable) - Already exists for general metrics refresh

**New fields added:**
- `youtube_refreshed_at` (timestamp, nullable) - When basic YouTube metrics were last updated
- `youtube_analytics_refreshed_at` (timestamp, nullable) - When video analytics were last updated

**artists table:**
- `youtube_channel_id` (string, nullable) - Already exists

### API Response Structure

**YouTube channels.list API response:**
```json
{
  "items": [
    {
      "id": "UCChannelId",
      "statistics": {
        "subscriberCount": "1234567",
        "videoCount": "890",
        "hiddenSubscriberCount": false
      },
      "contentDetails": {
        "relatedPlaylists": {
          "uploads": "UUChannelId"
        }
      }
    }
  ]
}
```

**YouTube playlistItems.list API response (for getting recent videos):**
```json
{
  "items": [
    {
      "contentDetails": {
        "videoId": "dQw4w9WgXcQ"
      }
    }
  ]
}
```

**YouTube videos.list API response (for video analytics):**
```json
{
  "items": [
    {
      "id": "dQw4w9WgXcQ",
      "statistics": {
        "viewCount": "1000000",
        "likeCount": "50000",
        "commentCount": "5000"
      }
    }
  ]
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: API Authentication and Request Structure
*For any* YouTube API request, the service should include a valid API key and follow the correct request format
**Validates: Requirements 1.1**

### Property 2: Quota Tracking and Enforcement
*For any* API request, the system should track quota usage and prevent requests when the daily limit is reached
**Validates: Requirements 1.2, 1.3, 6.1, 6.3**

### Property 3: Response Caching Behavior
*For any* successful API response, identical requests within 24 hours should return cached data without making new API calls
**Validates: Requirements 1.4**

### Property 4: Retry Logic with Exponential Backoff
*For any* transient API failure, the system should retry with exponential backoff up to the maximum retry limit
**Validates: Requirements 1.5, 3.3**

### Property 5: Comprehensive API Logging
*For any* API interaction, the system should generate appropriate log entries with sufficient context for debugging
**Validates: Requirements 1.6, 5.4**

### Property 6: Channel Metrics Fetching
*For any* artist with a valid youtube_channel_id, the service should fetch both subscriber count and video count in a single request
**Validates: Requirements 2.1, 2.2**

### Property 7: Batch Processing Optimization
*For any* collection of channel IDs, the service should process them in batches to minimize quota usage
**Validates: Requirements 2.3, 3.4**

### Property 8: Database Update Consistency
*For any* successful API response, the system should update the artist_metrics table and record the refresh timestamp
**Validates: Requirements 2.4, 2.5**

### Property 9: Graceful Error Handling
*For any* channel that is private, terminated, or not found, the system should handle the error gracefully and continue processing other channels
**Validates: Requirements 2.6, 5.3, 7.3**

### Property 10: Background Job Idempotency
*For any* background job execution, running the same job multiple times should produce the same result without side effects
**Validates: Requirements 3.2**

### Property 11: Job Failure Resilience
*For any* permanent job failure, the system should log the failure and continue processing other artists without stopping
**Validates: Requirements 3.5**

### Property 12: API Response Structure Consistency
*For any* artist API response, YouTube metrics (subscriber count and video count) should be included, with null values when no channel ID exists
**Validates: Requirements 4.1, 4.2, 4.5**

### Property 13: Automatic Refresh for Stale Data
*For any* artist request where YouTube data is older than 24 hours, the system should automatically trigger a refresh
**Validates: Requirements 4.4**

### Property 14: Graceful Degradation During Outages
*For any* YouTube API unavailability or quota exhaustion, the system should continue operating with cached data
**Validates: Requirements 5.1, 5.2**

### Property 15: Quota Monitoring and Alerting
*For any* quota usage that approaches 90% of the daily limit, the system should log warning messages
**Validates: Requirements 6.2**

### Property 16: Daily Quota Reset
*For any* day boundary at midnight UTC, the system should reset quota tracking counters
**Validates: Requirements 6.4**

### Property 17: Data Validation and Sanitization
*For any* YouTube API response, subscriber counts and video counts should be validated as non-negative integers
**Validates: Requirements 7.1, 7.2**

### Property 18: Channel ID Format Validation
*For any* channel ID used in API requests, it should follow the correct YouTube channel ID format
**Validates: Requirements 7.4**

### Property 19: Edge Case Handling
*For any* channel with zero subscribers or zero videos, the system should handle these values correctly without errors
**Validates: Requirements 7.5**

### Property 20: Video Analytics Collection
*For any* channel with videos, the system should fetch and analyze up to 15 recent videos for engagement metrics
**Validates: Requirements 8.1, 8.2**

### Property 21: Video Analytics Calculation
*For any* collection of video statistics, the system should calculate accurate average metrics (views, likes, comments)
**Validates: Requirements 8.3**

### Property 22: Partial Video Analytics Handling
*For any* channel with fewer than 15 videos, the system should analyze all available videos and record the actual count
**Validates: Requirements 8.4, 8.5**

### Property 23: Video Statistics Availability Handling
*For any* video with disabled statistics, the system should exclude it from calculations without failing
**Validates: Requirements 8.6**

### Property 24: Video Analytics Caching
*For any* video analytics data, the system should cache results separately from basic channel metrics
**Validates: Requirements 9.7**

### Property 25: Frontend YouTube Metrics Display
*For any* artist detail page request, the "Data & Metrics" tab should display YouTube subscriber count, average views, likes, and comments when available
**Validates: Requirements 5.1, 5.2, 5.3, 5.4**

### Property 26: Frontend Null Value Handling
*For any* artist without YouTube data, the metrics grid should display "N/A" or appropriate placeholder text instead of empty fields
**Validates: Requirements 5.5**

### Property 27: Frontend Refresh Integration
*For any* "Refresh Data" button click, the system should trigger refresh of both basic YouTube metrics and video analytics
**Validates: Requirements 5.7**

## Error Handling

### YouTube API Error Categories

**1. Authentication Errors (401)**
- Invalid or missing API key
- Response: Log error, throw YouTubeApiException
- Recovery: Check API key configuration

**2. Quota Exhaustion (403)**
- Daily quota limit exceeded
- Response: Cache quota exhaustion status, return cached data
- Recovery: Wait for daily quota reset at midnight UTC

**3. Rate Limiting (429)**
- Too many requests per second
- Response: Implement exponential backoff retry
- Recovery: Retry with increasing delays (1s, 2s, 4s, 8s)

**4. Channel Not Found (404)**
- Channel ID doesn't exist or is private
- Response: Log warning, set metrics to null
- Recovery: Continue processing other channels

**5. Service Unavailable (5xx)**
- YouTube API temporary outage
- Response: Retry with exponential backoff
- Recovery: Fall back to cached data after max retries

### Error Recovery Strategies

```php
// Pseudo-code for error handling
try {
    $response = $this->makeYouTubeRequest($channelIds);
    return $this->processResponse($response);
} catch (QuotaExhaustedException $e) {
    $this->cacheQuotaExhaustion();
    return $this->getCachedData($channelIds);
} catch (RateLimitException $e) {
    return $this->retryWithBackoff($channelIds, $attempt);
} catch (ChannelNotFoundException $e) {
    $this->logChannelNotFound($e->getChannelId());
    return $this->setMetricsToNull($e->getChannelId());
}
```

### Graceful Degradation

When YouTube integration fails:
1. **API Unavailable**: Return last known cached values
2. **Quota Exhausted**: Show cached data with staleness indicator
3. **Invalid Channel**: Display null metrics without breaking UI
4. **Network Issues**: Continue with Spotify-only scoring

## Testing Strategy

### Dual Testing Approach

The YouTube integration will use both unit tests and property-based tests for comprehensive coverage:

**Unit Tests:**
- Test specific API response parsing scenarios
- Test error handling for known edge cases
- Test configuration and setup logic
- Test integration points with existing controllers

**Property-Based Tests:**
- Test quota tracking across random API call sequences
- Test batch processing with varying channel ID collections
- Test caching behavior with random time intervals
- Test retry logic with simulated failure patterns
- Test data validation with generated invalid inputs

### Property-Based Testing Configuration

- **Testing Library**: Use Pest with custom property test generators
- **Minimum Iterations**: 100 iterations per property test
- **Test Tagging**: Each property test references its design document property
- **Tag Format**: `Feature: youtube-integration, Property {number}: {property_text}`

### Mock Strategy

**Never hit real YouTube API in tests** - always use `Http::fake()`:

```php
// Example test setup
Http::fake([
    'www.googleapis.com/youtube/v3/channels*' => Http::response([
        'items' => [
            [
                'id' => 'UCChannelId',
                'statistics' => [
                    'subscriberCount' => '1000000',
                    'videoCount' => '500'
                ]
            ]
        ]
    ])
]);
```

### Test Coverage Requirements

- **Service Layer**: 100% coverage of YouTubeService methods
- **Job Processing**: 100% coverage of FetchYouTubeDataJob
- **Error Handling**: All exception paths tested
- **Integration**: API endpoints include YouTube data
- **Property Tests**: All 19 correctness properties implemented

## Implementation Notes

### YouTube API Specifics

**Quota Costs:**
- `channels.list` request: 1 unit per request (regardless of number of channels)
- `playlistItems.list` request: 1 unit per request (to get recent video IDs)
- `videos.list` request: 1 unit per request (to get video statistics)
- **Total cost per channel with video analytics**: ~3 units (channel + playlist + videos)
- Daily limit: 10,000 units (can request quota increase)
- **Estimated capacity**: ~3,300 channels with full analytics per day

**API Endpoints:**
```
# Get channel basic info and uploads playlist ID
GET https://www.googleapis.com/youtube/v3/channels
?part=statistics,contentDetails
&id=UCChannelId1,UCChannelId2
&key=YOUR_API_KEY

# Get recent video IDs from uploads playlist
GET https://www.googleapis.com/youtube/v3/playlistItems
?part=contentDetails
&playlistId=UUChannelId
&maxResults=15
&key=YOUR_API_KEY

# Get video statistics for analytics
GET https://www.googleapis.com/youtube/v3/videos
?part=statistics
&id=videoId1,videoId2,videoId3...
&key=YOUR_API_KEY
```

**Response Formats:**
```json
// Channel info response
{
  "items": [
    {
      "id": "UCChannelId1",
      "statistics": {
        "subscriberCount": "1234567",
        "videoCount": "890",
        "hiddenSubscriberCount": false
      },
      "contentDetails": {
        "relatedPlaylists": {
          "uploads": "UUChannelId1"
        }
      }
    }
  ]
}

// Recent videos response
{
  "items": [
    {
      "contentDetails": {
        "videoId": "dQw4w9WgXcQ"
      }
    }
  ]
}

// Video statistics response
{
  "items": [
    {
      "id": "dQw4w9WgXcQ",
      "statistics": {
        "viewCount": "1000000",
        "likeCount": "50000",
        "commentCount": "5000"
      }
    }
  ]
}
```

### Batch Processing Strategy

To optimize quota usage with video analytics:
1. **Channel Batch Size**: Process up to 50 channels per basic info request (1 unit)
2. **Video Analytics**: Process channels individually for video analytics (2-3 units each)
3. **Priority System**: 
   - High priority: Channels with stale basic metrics (>24 hours)
   - Medium priority: Channels with stale video analytics (>7 days)
   - Low priority: Channels needing first-time video analytics
4. **Quota Allocation**:
   - 70% for basic channel metrics (high volume, low cost)
   - 30% for video analytics (lower volume, higher cost)
5. **Job Chunking**: Background jobs process in mixed batches to balance quota usage

### Caching Strategy

**Cache Keys:**
- Channel basic metrics: `youtube_channel:{channelId}`
- Video analytics: `youtube_analytics:{channelId}`
- Recent video IDs: `youtube_videos:{channelId}`
- Quota usage: `youtube_quota:{date}`
- Quota exhaustion: `youtube_quota_exhausted`

**Cache TTL:**
- Basic channel metrics: 24 hours
- Video analytics: 7 days (less frequent updates needed)
- Recent video IDs: 24 hours
- Failed responses: 1 hour
- Quota tracking: Until midnight UTC

### Integration with Existing Systems

**ArtistController Updates:**
- Include YouTube metrics in artist API responses
- Include YouTube video analytics in artist API responses
- Trigger YouTube refresh in existing refresh endpoint
- Handle YouTube data in artist search results

**Frontend Updates:**
- Update Artist Detail page "Data & Metrics" tab to display YouTube metrics
- Add YouTube subscriber count to metrics grid
- Add YouTube average views per video to metrics grid
- Add YouTube average likes per video to metrics grid
- Add YouTube average comments per video to metrics grid
- Update "Refresh Data" button to refresh YouTube data
- Handle null YouTube values with appropriate placeholders

**ArtistScoringService Integration:**
- Include YouTube subscriber count in score calculations
- Consider including video analytics in future scoring enhancements
- Handle null YouTube metrics gracefully
- Maintain backward compatibility with existing scoring

**Database Considerations:**
- Add new fields for video analytics in artist_metrics table
- Use existing `youtube_subscribers` field for basic metrics
- Add separate timestamp for video analytics refresh tracking
- Consider indexing on analytics refresh timestamp for efficient querying

# Design Document: VEVO Channel Detection and Replacement

## Overview

This document outlines the design for implementing VEVO YouTube channel detection and automatic replacement with primary artist channels. The system will identify artists currently linked to VEVO channels (which typically have no videos and redirect users) and automatically promote the artist's main YouTube channel with the highest subscriber count as the primary channel ID. The detection process is triggered when users search for artists, ensuring processing only happens for actively viewed content.

## Architecture

The VEVO detection system integrates with the existing artist search flow and YouTube integration:

```
┌─────────────────┐    ┌──────────────────────┐    ┌─────────────────┐
│   Search Flow   │───▶│  VEVO Detection      │───▶│  YouTube API    │
│                 │    │  Services            │    │                 │
│ ArtistController│    │                      │    │ Search & Channel│
│ SearchService   │    │ VEVOChannelDetection │    │ Data APIs       │
└─────────────────┘    │ YouTubeChannelSearch │    └─────────────────┘
         │              │ ChannelRanking      │             │
         ▼              └──────────────────────┘             ▼
┌─────────────────┐              │                ┌─────────────────┐
│   Background    │◀─────────────┘                │     Database    │
│     Jobs        │                               │                 │
│                 │                               │ artists table   │
│VEVOChannelRepl  │                               │ artist_links    │
│ acementJob      │                               │ audit logs      │
└─────────────────┘                               └─────────────────┘
```

## Components and Interfaces

### VEVOChannelDetectionService

The core service responsible for identifying VEVO channels.

**Class:** `App\Services\VEVOChannelDetectionService`

**Key Methods:**
- `isVEVOChannel(YouTubeChannelDTO $channel): bool`
- `detectVEVOChannelsForArtist(Artist $artist): bool`
- `shouldCheckArtist(Artist $artist): bool`
- `markArtistAsChecked(Artist $artist): void`
- `getVEVODetectionPatterns(): array`

**Detection Logic:**
```php
public function shouldCheckArtist(Artist $artist): bool
{
    // Skip if already checked within last 7 days
    if ($artist->youtube_link && $artist->youtube_link->vevo_checked_at) {
        $daysSinceCheck = now()->diffInDays($artist->youtube_link->vevo_checked_at);
        if ($daysSinceCheck < 7) {
            return false;
        }
    }
    
    return true;
}

public function detectVEVOChannelsForArtist(Artist $artist): bool
{
    if (!$this->shouldCheckArtist($artist)) {
        return false; // Already checked recently
    }
    
    // Perform VEVO detection logic...
    $isVEVO = $this->isVEVOChannel($artist->youtube_channel);
    
    // Mark as checked regardless of result
    $this->markArtistAsChecked($artist);
    
    return $isVEVO;
}

public function markArtistAsChecked(Artist $artist): void
{
    if ($artist->youtube_link) {
        $artist->youtube_link->update(['vevo_checked_at' => now()]);
    }
}

public function isVEVOChannel(YouTubeChannelDTO $channel): bool
{
    // Check channel name for VEVO patterns
    if (stripos($channel->title, 'VEVO') !== false) {
        return true;
    }
    
    // Check for zero videos (VEVO channels typically have no content)
    if ($channel->videoCount === 0) {
        return true;
    }
    
    // Check description for VEVO mentions or redirection language
    if ($this->containsVEVOPatterns($channel->description)) {
        return true;
    }
    
    return false;
}
```

### YouTubeChannelSearchService

Service for discovering alternative YouTube channels for artists.

**Class:** `App\Services\YouTubeChannelSearchService`

**Key Methods:**
- `searchChannelsForArtist(Artist $artist): array`
- `searchByQuery(string $query): array`
- `filterVEVOChannels(array $channels): array`
- `validateChannelOwnership(YouTubeChannelDTO $channel, Artist $artist): bool`

**Search Strategy:**
```php
public function searchChannelsForArtist(Artist $artist): array
{
    $candidates = [];
    
    // Search using primary artist name
    $candidates = array_merge($candidates, $this->searchByQuery($artist->name));
    
    // Search using artist aliases
    foreach ($artist->aliases as $alias) {
        $candidates = array_merge($candidates, $this->searchByQuery($alias->name));
    }
    
    // Filter out VEVO channels and duplicates
    $candidates = $this->filterVEVOChannels($candidates);
    $candidates = $this->removeDuplicates($candidates);
    
    // Limit to 10 candidates to manage API quota
    return array_slice($candidates, 0, 10);
}
```

### YouTubeChannelRankingAlgorithm

Algorithm for ranking and selecting the best alternative channel.

**Class:** `App\Services\YouTubeChannelRankingAlgorithm`

**Key Methods:**
- `rankChannels(array $channels): array`
- `calculateChannelScore(YouTubeChannelDTO $channel): float`
- `selectBestChannel(array $channels): ?YouTubeChannelDTO`

**Ranking Logic:**
```php
public function calculateChannelScore(YouTubeChannelDTO $channel): float
{
    $score = $channel->subscriberCount; // Base score from subscribers
    
    // Bonus for verified channels (20% boost)
    if ($channel->isVerified) {
        $score *= 1.2;
    }
    
    // Bonus for recent activity (10% boost if uploaded within 6 months)
    if ($channel->hasRecentActivity) {
        $score *= 1.1;
    }
    
    // Penalty for very low subscriber count
    if ($channel->subscriberCount < 1000) {
        $score = 0; // Exclude from consideration
    }
    
    return $score;
}
```

### VEVOChannelReplacementJob

Background job for processing individual VEVO channel replacements.

**Class:** `App\Jobs\VEVOChannelReplacementJob`

**Properties:**
- `Artist $artist` - The artist to process
- `int $maxRetries = 3` - Maximum retry attempts
- `int $retryDelay = 60` - Delay between retries in seconds

**Responsibilities:**
- Detect if artist has VEVO channel
- Search for alternative channels
- Rank and select best replacement
- Update database with new channel
- Create audit trail
- Handle API errors gracefully

### YouTubeChannelDTO Extensions

Extend the existing DTO to support VEVO detection needs.

**Class:** `App\DataTransferObjects\YouTubeChannelDTO`

**Additional Properties:**
- `bool $isVerified` - Whether channel has verification badge
- `bool $hasRecentActivity` - Whether channel uploaded within 6 months
- `?DateTime $lastUploadDate` - Date of most recent video upload
- `string $description` - Channel description text

## Data Models

### Database Schema Updates

**artist_links table additions:**
```sql
-- Add review status column for future manual review functionality
ALTER TABLE artist_links ADD COLUMN review_status ENUM(
    'public_added', 
    'admin_added', 
    'pending_approval', 
    'approved'
) DEFAULT 'public_added' AFTER url;

-- Add column to track VEVO detection processing
ALTER TABLE artist_links ADD COLUMN vevo_checked_at TIMESTAMP NULL AFTER review_status;
```

### API Response Structure

**YouTube Search API Integration:**
```json
{
  "kind": "youtube#searchListResponse",
  "items": [
    {
      "kind": "youtube#searchResult",
      "id": {
        "kind": "youtube#channel",
        "channelId": "UCChannelId"
      },
      "snippet": {
        "title": "Artist Name",
        "description": "Official channel description...",
        "thumbnails": {...}
      }
    }
  ]
}
```

**YouTube Channels API for detailed info:**
```json
{
  "items": [
    {
      "id": "UCChannelId",
      "statistics": {
        "subscriberCount": "1234567",
        "videoCount": "890"
      },
      "status": {
        "privacyStatus": "public"
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

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: VEVO Channel Name Detection
*For any* YouTube channel, if the channel title contains "VEVO" (case-insensitive), it should be identified as a VEVO channel
**Validates: Requirements 1.1**

### Property 2: Zero Video VEVO Detection
*For any* YouTube channel with exactly zero videos, it should be flagged as a potential VEVO channel
**Validates: Requirements 1.2**

### Property 3: VEVO Description Pattern Detection
*For any* YouTube channel description containing VEVO-related keywords or redirection language, the channel should be identified as VEVO
**Validates: Requirements 1.3**

### Property 4: Artist Name Search Query
*For any* artist, the channel search service should use the artist's name as a search query to the YouTube API
**Validates: Requirements 2.1**

### Property 5: Artist Alias Search Coverage
*For any* artist with aliases, the search service should perform separate searches for each alias name
**Validates: Requirements 2.2**

### Property 6: VEVO Channel Exclusion from Candidates
*For any* search results containing VEVO channels, those channels should be filtered out from replacement candidates
**Validates: Requirements 2.3**

### Property 7: Subscriber Count Ranking Priority
*For any* collection of candidate channels, they should be ranked with higher subscriber counts appearing first
**Validates: Requirements 3.1**

### Property 8: Verified Channel Ranking Bonus
*For any* two channels with similar subscriber counts, the verified channel should rank higher
**Validates: Requirements 3.2**

### Property 9: Verified Channel Tie-Breaking
*For any* channels with subscriber counts within 10% of each other, the verified channel should be selected
**Validates: Requirements 3.6**

### Property 10: Database Update on Replacement
*For any* successful channel replacement, the artist's youtube_channel_id should be updated in the database
**Validates: Requirements 4.1**

### Property 11: Minimum Subscriber Threshold
*For any* replacement candidate, automatic replacement should only occur if the channel has at least 1000 subscribers
**Validates: Requirements 4.5**

### Property 12: Pending Approval Status Assignment
*For any* automatically replaced VEVO channel, the new link should be marked with review_status "pending_approval"
**Validates: Requirements 5.3**

### Property 13: Search-Triggered VEVO Detection
*For any* artist appearing in search results, the system should check if the artist has a VEVO channel
**Validates: Requirements 6.1**

### Property 14: API Response Channel Update
*For any* artist with a replaced channel, API responses should immediately return data for the new channel
**Validates: Requirements 7.1**

### Property 15: API Error Retry Logic
*For any* YouTube API error, the system should retry with exponential backoff up to 3 times
**Validates: Requirements 8.1**

### Property 16: Error Isolation in Processing
*For any* batch of artists being processed, individual replacement failures should not prevent processing of other artists
**Validates: Requirements 8.6**

### Property 17: Replacement Quality Validation
*For any* proposed replacement channel, it should have significantly more subscribers than the original VEVO channel
**Validates: Requirements 9.1**

### Property 18: Recent Activity Validation
*For any* replacement channel, it should have uploaded video content within the last 12 months
**Validates: Requirements 9.2**

## Error Handling

### YouTube API Error Categories

**1. Search API Errors (400)**
- Invalid search query or parameters
- Response: Log error, skip artist, continue processing
- Recovery: Validate search parameters before API calls

**2. Quota Exhaustion (403)**
- Daily quota limit exceeded for search or channel APIs
- Response: Cache quota exhaustion, pause processing
- Recovery: Resume processing after quota reset

**3. Channel Not Found (404)**
- Channel ID doesn't exist or became private
- Response: Log warning, mark as failed replacement
- Recovery: Continue with next candidate channel

**4. Rate Limiting (429)**
- Too many requests per second
- Response: Implement exponential backoff
- Recovery: Retry with increasing delays

**5. Service Unavailable (5xx)**
- YouTube API temporary outage
- Response: Retry with exponential backoff
- Recovery: Fall back to cached data, alert administrators

### Error Recovery Strategies

```php
// Pseudo-code for error handling in replacement job
try {
    $candidates = $this->searchService->searchChannelsForArtist($artist);
    $bestChannel = $this->rankingAlgorithm->selectBestChannel($candidates);
    $this->replaceChannel($artist, $bestChannel);
} catch (QuotaExhaustedException $e) {
    $this->logQuotaExhaustion();
    $this->release(3600); // Retry in 1 hour
} catch (YouTubeApiException $e) {
    $this->handleApiError($e, $artist);
    if ($this->attempts() < 3) {
        $this->release($this->calculateBackoffDelay());
    }
} catch (Exception $e) {
    $this->logUnexpectedError($e, $artist);
    $this->fail($e);
}
```

### Graceful Degradation

When VEVO detection fails:
1. **API Unavailable**: Skip processing, continue with existing channel
2. **Search Fails**: Log issue, mark artist for retry
3. **No Candidates Found**: Keep original channel, log for manual review
4. **Replacement Fails**: Rollback to original channel, create audit entry

## Testing Strategy

### Dual Testing Approach

The VEVO detection system will use both unit tests and property-based tests for comprehensive coverage:

**Unit Tests:**
- Test specific VEVO detection patterns
- Test API response parsing scenarios
- Test error handling for known edge cases
- Test database update operations
- Test integration with search flow

**Property-Based Tests:**
- Test VEVO detection across random channel data
- Test ranking algorithm with varying subscriber counts
- Test search behavior with random artist names and aliases
- Test error handling with simulated API failures
- Test replacement logic with generated channel candidates

### Property-Based Testing Configuration

- **Testing Library**: Use Pest with custom property test generators for PHP
- **Minimum Iterations**: 100 iterations per property test
- **Test Tagging**: Each property test references its design document property
- **Tag Format**: `Feature: vevo-channel-detection, Property {number}: {property_text}`

### Mock Strategy

**Never hit real YouTube API in tests** - always use `Http::fake()`:

```php
// Example test setup for channel search
Http::fake([
    'www.googleapis.com/youtube/v3/search*' => Http::response([
        'items' => [
            [
                'kind' => 'youtube#searchResult',
                'id' => ['kind' => 'youtube#channel', 'channelId' => 'UCTestChannel'],
                'snippet' => [
                    'title' => 'Test Artist',
                    'description' => 'Official channel'
                ]
            ]
        ]
    ]),
    'www.googleapis.com/youtube/v3/channels*' => Http::response([
        'items' => [
            [
                'id' => 'UCTestChannel',
                'statistics' => [
                    'subscriberCount' => '50000',
                    'videoCount' => '100'
                ]
            ]
        ]
    ])
]);
```

### Test Coverage Requirements

- **Service Layer**: 100% coverage of all VEVO detection services
- **Job Processing**: 100% coverage of VEVOChannelReplacementJob
- **Error Handling**: All exception paths tested
- **Integration**: Search flow integration tested
- **Property Tests**: All 18 correctness properties implemented

## Implementation Notes

### YouTube API Integration

**Search API Usage:**
```
# Search for channels by artist name
GET https://www.googleapis.com/youtube/v3/search
?part=snippet
&type=channel
&q={artist_name}
&maxResults=10
&key=YOUR_API_KEY
```

**Channel Details API:**
```
# Get detailed channel information
GET https://www.googleapis.com/youtube/v3/channels
?part=statistics,status,contentDetails
&id={channel_ids}
&key=YOUR_API_KEY
```

**Quota Costs:**
- Search API: 100 units per request
- Channels API: 1 unit per request
- **Total per artist**: ~101 units (1 search + 1 channel details)
- **Daily capacity**: ~99 artists with full processing

### Integration with Existing Search Flow

**ArtistController Integration:**
```php
// In existing search method
public function search(SearchArtistsRequest $request)
{
    $artists = $this->artistSearchService->search($request->query);
    
    // Trigger VEVO detection for search results (only if not recently checked)
    foreach ($artists as $artist) {
        if ($artist->youtube_channel_id && app(VEVOChannelDetectionService::class)->shouldCheckArtist($artist)) {
            VEVOChannelReplacementJob::dispatch($artist)->onQueue('vevo-detection');
        }
    }
    
    return ArtistSearchResultResource::collection($artists);
}
```

### Caching Strategy

**Cache Keys:**
- VEVO detection results: `vevo_detection:{artist_id}`
- Channel search results: `channel_search:{artist_name}`
- Ranking results: `channel_ranking:{artist_id}`

**Cache TTL:**
- VEVO detection results: 7 days
- Channel search results: 24 hours
- Failed detections: 1 hour

### Performance Considerations

**Async Processing:**
- VEVO detection runs in background jobs
- Search responses are not blocked
- Queue workers process replacements independently

**Batch Optimization:**
- Process multiple channel details in single API call
- Limit concurrent jobs to manage quota usage
- Prioritize high-engagement artists

**Database Optimization:**
- Index on artist_id and processed_at for audit queries
- Use database transactions for atomic replacements
- Implement soft deletes for audit trail preservation
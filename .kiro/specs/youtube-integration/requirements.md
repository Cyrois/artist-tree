# Requirements Document

## Introduction

This document outlines the requirements for implementing YouTube API integration in the Artist-Tree platform. The YouTube integration will fetch channel metrics (subscriber counts, video counts) for artists who already have YouTube channel IDs stored in the database, enhancing artist scoring calculations.

## Glossary

- **YouTube_Service**: The service class responsible for YouTube API interactions
- **Artist_Metrics**: Database table storing quantitative metrics for artists
- **Quota_Management**: YouTube API daily quota tracking and management system
- **Graceful_Degradation**: System behavior when YouTube API is unavailable or returns errors

## Requirements

### Requirement 1: YouTube API Service Implementation

**User Story:** As a system administrator, I want a robust YouTube API service, so that the platform can reliably fetch YouTube channel data for artists.

#### Acceptance Criteria

1. THE YouTube_Service SHALL authenticate with YouTube Data API v3 using API key
2. WHEN making API requests, THE YouTube_Service SHALL implement rate limiting to respect YouTube's quota limits of 10000 per day
3. WHEN API quota is exceeded, THE YouTube_Service SHALL cache the quota status and prevent further requests until reset
4. THE YouTube_Service SHALL cache successful API responses for 24 hours to minimize quota usage
5. WHEN API errors occur, THE YouTube_Service SHALL implement exponential backoff retry logic for transient failures
6. THE YouTube_Service SHALL log all API interactions with appropriate context for debugging

### Requirement 2: YouTube Channel Data Fetching

**User Story:** As a system, I want to fetch YouTube channel metrics for artists with known channel IDs, so that I can populate YouTube metrics for scoring calculations.

#### Acceptance Criteria

1. THE YouTube_Service SHALL fetch subscriber count for artists with existing youtube_channel_id values
2. THE YouTube_Service SHALL fetch video count for artists with existing youtube_channel_id values
3. THE YouTube_Service SHALL support batch fetching of multiple channels in a single API request to optimize quota usage
4. WHEN channel data is successfully fetched, THE system SHALL update the artist_metrics table with current values
5. THE system SHALL record the timestamp when YouTube metrics were last refreshed
6. WHEN a channel is private, terminated, or not found, THE system SHALL handle this gracefully and log the issue

### Requirement 3: Background Job Processing

**User Story:** As a system administrator, I want YouTube data fetching to happen asynchronously, so that user-facing operations remain fast and responsive.

#### Acceptance Criteria

1. THE system SHALL dispatch background jobs to fetch YouTube data for artists with youtube_channel_id values
2. THE background job SHALL be idempotent and handle duplicate processing gracefully
3. WHEN YouTube API is unavailable, THE job SHALL retry with exponential backoff up to 3 times
4. THE job SHALL process multiple artists in batches to optimize API quota usage
5. WHEN job processing fails permanently, THE system SHALL log the failure and continue processing other artists

### Requirement 4: API Integration Points

**User Story:** As a frontend developer, I want API endpoints to include YouTube data, so that I can display comprehensive artist information to users.

#### Acceptance Criteria

1. WHEN returning artist data, THE API SHALL include YouTube subscriber count in the metrics object
2. WHEN returning artist data, THE API SHALL include YouTube video count in the metrics object
3. WHEN returning artist data, THE API SHALL include YouTube video analytics (average views, likes, comments) in the metrics object
4. THE artist refresh endpoint SHALL trigger YouTube data refresh alongside Spotify data
5. WHEN YouTube data is stale (older than 24 hours), THE system SHALL automatically refresh it during artist requests
6. THE API SHALL return YouTube metrics as null when no channel ID exists rather than omitting the fields

### Requirement 5: Frontend Display Integration

**User Story:** As a user, I want to see YouTube metrics on the artist detail page, so that I can assess an artist's YouTube performance alongside other metrics.

#### Acceptance Criteria

1. THE artist detail page "Data & Metrics" tab SHALL display YouTube subscriber count in the metrics grid
2. THE artist detail page "Data & Metrics" tab SHALL display YouTube average views per video in the metrics grid
3. THE artist detail page "Data & Metrics" tab SHALL display YouTube average likes per video in the metrics grid
4. THE artist detail page "Data & Metrics" tab SHALL display YouTube average comments per video in the metrics grid
5. WHEN YouTube metrics are null, THE system SHALL display "N/A" or appropriate placeholder text
6. THE metrics grid SHALL show the refresh timestamp for YouTube data
7. THE "Refresh Data" button SHALL trigger refresh of both basic YouTube metrics and video analytics

### Requirement 6: Error Handling and Resilience

**User Story:** As a user, I want the platform to work reliably even when YouTube API has issues, so that my workflow is not disrupted.

#### Acceptance Criteria

1. WHEN YouTube API is unavailable, THE system SHALL continue operating with existing cached data
2. WHEN YouTube quota is exceeded, THE system SHALL gracefully degrade and show last known values
3. WHEN YouTube channel is deleted or made private, THE system SHALL handle this gracefully and update the metrics to null
4. THE system SHALL provide clear error messages in logs when YouTube integration fails
5. WHEN YouTube data cannot be fetched, THE artist scoring system SHALL continue using available metrics

### Requirement 7: Quota Management and Monitoring

**User Story:** As a system administrator, I want to monitor YouTube API usage, so that I can ensure we stay within quota limits and optimize usage.

#### Acceptance Criteria

1. THE system SHALL track daily YouTube API quota usage in cache
2. WHEN quota usage approaches the daily limit (90%), THE system SHALL log warnings
3. THE system SHALL prevent new API requests when quota is exhausted
4. THE system SHALL reset quota tracking at midnight UTC each day
5. THE system SHALL provide visibility into quota usage through logs and metrics

### Requirement 8: Data Consistency and Validation

**User Story:** As a data analyst, I want YouTube data to be accurate and consistent, so that artist scoring and analytics are reliable.

#### Acceptance Criteria

1. THE system SHALL validate that subscriber counts are non-negative integers
2. THE system SHALL validate that video counts are non-negative integers
3. WHEN YouTube returns invalid data, THE system SHALL log the issue and skip the update
4. THE system SHALL ensure YouTube channel IDs follow the correct format before making API requests
5. THE system SHALL handle edge cases like channels with 0 subscribers or 0 videos correctly

### Requirement 9: Video Analytics Collection

**User Story:** As a data analyst, I want to collect engagement metrics from recent videos, so that I can assess artist performance beyond just subscriber counts.

#### Acceptance Criteria

1. THE YouTube_Service SHALL fetch the last 15 videos from each artist's channel
2. THE system SHALL collect view count, like count, and comment count for each video
3. THE system SHALL calculate average metrics across the analyzed videos
4. WHEN a channel has fewer than 15 videos, THE system SHALL analyze all available videos
5. THE system SHALL store the number of videos analyzed alongside the average metrics
6. THE system SHALL handle videos with disabled statistics gracefully by excluding them from calculations
7. THE system SHALL cache video analytics separately from basic channel metrics with appropriate TTL
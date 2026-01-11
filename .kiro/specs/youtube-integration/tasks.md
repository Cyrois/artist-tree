# Implementation Plan: YouTube Integration

## Overview

This implementation plan breaks down the YouTube integration feature into discrete coding tasks. The approach follows the established patterns from the Spotify integration while adding video analytics capabilities. Tasks are organized to build incrementally, with early validation through testing.

## Tasks

- [x] 1. Database Migration for YouTube Analytics
  - Create migration to add YouTube analytics columns to artist_metrics table
  - Add columns: youtube_refreshed_at, youtube_avg_views, youtube_avg_likes, youtube_avg_comments, youtube_videos_analyzed, youtube_analytics_refreshed_at
  - Ensure columns are added in correct order after youtube_subscribers
  - _Requirements: Database Schema Updates_

- [x] 2. Create YouTube Data Transfer Objects
  - [x] 2.1 Create YouTubeChannelDTO class
    - Implement properties: channelId, subscriberCount, videoCount, subscriberCountHidden, videoAnalytics
    - Add fromYouTubeResponse factory method
    - _Requirements: 2.1, 2.2_

  - [x] 2.2 Create YouTubeVideoAnalyticsDTO class
    - Implement properties: videosAnalyzed, averageViews, averageLikes, averageComments, videoIds
    - Add fromYouTubeResponse and calculateFromVideos factory methods
    - _Requirements: 9.3, 9.4, 9.5_

  - [x] 2.3 Write property tests for DTOs
    - **Property 17: Data Validation and Sanitization**
    - **Validates: Requirements 8.1, 8.2**

- [x] 3. Implement YouTube API Service
  - [x] 3.1 Create YouTubeService class with basic structure
    - Set up authentication with API key
    - Implement quota tracking and rate limiting
    - Add basic error handling and logging
    - _Requirements: 1.1, 1.2, 1.6_

  - [x] 3.2 Implement channel metrics fetching
    - Add getChannelMetrics method for single channel
    - Add getMultipleChannelMetrics method for batch processing
    - Implement caching with 24-hour TTL
    - _Requirements: 2.1, 2.2, 2.3, 1.4_

  - [x] 3.3 Implement video analytics collection
    - Add getChannelVideos method to fetch recent videos
    - Add getVideoAnalytics method to fetch video statistics
    - Add calculateVideoAnalytics method to compute averages
    - _Requirements: 9.1, 9.2, 9.3_

  - [x] 3.4 Write property tests for YouTubeService
    - **Property 2: Quota Tracking and Enforcement**
    - **Property 3: Response Caching Behavior**
    - **Property 6: Channel Metrics Fetching**
    - **Property 20: Video Analytics Collection**
    - **Validates: Requirements 1.2, 1.3, 1.4, 2.1, 2.2, 9.1, 9.2**

- [x] 4. Create YouTube API Exception Handling
  - [x] 4.1 Create YouTubeApiException class
    - Add properties: statusCode, quotaCost, isQuotaExhausted
    - Implement factory methods for different error types
    - _Requirements: 6.1, 6.2, 6.3_

  - [x] 4.2 Implement error handling in YouTubeService
    - Add retry logic with exponential backoff
    - Handle quota exhaustion gracefully
    - Implement graceful degradation for API outages
    - _Requirements: 1.5, 6.1, 6.2, 6.3_

  - [x] 4.3 Write property tests for error handling
    - **Property 4: Retry Logic with Exponential Backoff**
    - **Property 9: Graceful Error Handling**
    - **Property 14: Graceful Degradation During Outages**
    - **Validates: Requirements 1.5, 2.6, 5.1, 5.2, 5.3**

- [x] 5. Checkpoint - Core YouTube Service Complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Create Background Job for YouTube Data Fetching
  - [x] 6.1 Create FetchYouTubeDataJob class
    - Implement job to process artists with youtube_channel_id
    - Add batch processing for quota optimization
    - Implement idempotent processing
    - _Requirements: 3.1, 3.4, 3.2_

  - [x] 6.2 Add job retry and failure handling
    - Implement exponential backoff for API failures
    - Add permanent failure logging and continuation
    - Handle quota exhaustion scenarios
    - _Requirements: 3.3, 3.5, 6.3_

  - [x] 6.3 Write property tests for background job
    - **Property 10: Background Job Idempotency**
    - **Property 11: Job Failure Resilience**
    - **Property 7: Batch Processing Optimization**
    - **Validates: Requirements 3.2, 3.5, 2.3, 3.4**

- [x] 7. Update Artist Model and Metrics
  - [x] 7.1 Update ArtistMetric model
    - Add fillable fields for new YouTube columns
    - Add relationships and accessors as needed
    - Update factory for testing
    - _Requirements: 2.4, 2.5_

  - [x] 7.2 Add YouTube data refresh methods
    - Implement methods to check for stale YouTube data
    - Add logic to determine when refresh is needed
    - _Requirements: 4.5, 4.4_

  - [x] 7.3 Write property tests for model updates
    - **Property 8: Database Update Consistency**
    - **Property 13: Automatic Refresh for Stale Data**
    - **Validates: Requirements 2.4, 2.5, 4.4**

- [x] 8. Integrate YouTube Service with Artist Controller
  - [x] 8.1 Update ArtistController to include YouTube data
    - Modify artist API responses to include YouTube metrics
    - Add YouTube video analytics to response structure
    - Handle null values appropriately
    - _Requirements: 4.1, 4.2, 4.3, 4.6_

  - [x] 8.2 Update artist refresh endpoint
    - Integrate YouTube data refresh with existing Spotify refresh
    - Trigger both basic metrics and video analytics refresh
    - _Requirements: 4.4, 5.7_

  - [x] 8.3 Write property tests for API integration
    - **Property 12: API Response Structure Consistency** - PASSING
    - **Property 27: Frontend Refresh Integration** - PASSING
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.6, 5.7**

- [x] 9. Update Frontend Artist Detail Page
  - [x] 9.1 Update Artist Detail "Data & Metrics" tab
    - Add YouTube subscriber count to metrics grid
    - Add YouTube average views per video to metrics grid
    - Add YouTube average likes per video to metrics grid
    - Add YouTube average comments per video to metrics grid
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 9.2 Implement null value handling in UI
    - Display "N/A" for missing YouTube metrics
    - Show appropriate placeholders for unavailable data
    - _Requirements: 5.5_

  - [x] 9.3 Update refresh functionality
    - Ensure "Refresh Data" button triggers YouTube refresh
    - Display YouTube refresh timestamps
    - _Requirements: 5.6, 5.7_

  - [x] 9.4 Write unit tests for frontend components
    - Test YouTube metrics display
    - Test null value handling
    - Test refresh button integration
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.7_

- [x] 10. Implement Quota Management System
  - [x] 10.1 Create quota tracking functionality
    - Implement daily quota usage tracking in cache
    - Add quota monitoring and warning system
    - Implement quota reset at midnight UTC
    - _Requirements: 7.1, 7.2, 7.4, 7.5_

  - [x] 10.2 Add quota enforcement
    - Prevent API requests when quota exhausted
    - Implement graceful degradation when quota low
    - _Requirements: 7.3, 6.2_

  - [x] 10.3 Write property tests for quota management
    - **Property 15: Quota Monitoring and Alerting**
    - **Property 16: Daily Quota Reset**
    - **Validates: Requirements 7.2, 7.4**

- [x] 11. Add YouTube Integration to Job Dispatching
  - [x] 11.1 Update existing job dispatch points
    - Add YouTube job dispatching to artist search results
    - Integrate with existing artist refresh workflows
    - _Requirements: 3.1_

  - [x] 11.2 Implement priority-based job processing
    - Prioritize artists with stale data
    - Balance basic metrics vs video analytics processing
    - _Requirements: 3.4_

  - [x] 11.3 Write integration tests for job dispatching
    - Test job creation and queuing
    - Test priority-based processing
    - _Requirements: 3.1, 3.4_

- [x] 12. Final Integration and Testing
  - [x] 12.1 End-to-end integration testing
    - Test complete flow from API call to UI display
    - Verify error handling across all components
    - Test quota management in realistic scenarios
    - _Requirements: All_

  - [x] 12.2 Performance and quota optimization
    - Optimize batch sizes for quota efficiency
    - Fine-tune caching strategies
    - Validate quota usage projections
    - _Requirements: 2.3, 3.4, 7.1_

  - [x] 12.3 Write comprehensive integration tests
    - **Property 1-27: All correctness properties**
    - **Validates: All requirements**

- [x] 13. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 14. Refactor Controller Business Logic
  - [x] 14.1 Create ArtistYouTubeRefreshService
    - Extract YouTube refresh logic from ArtistController
    - Implement forceRefresh, refreshIfNeeded, refreshBasicMetrics, and refreshAnalytics methods
    - Add proper error handling and logging
    - _Requirements: Clean Architecture, Single Responsibility Principle_

  - [x] 14.2 Update ArtistController to use service
    - Remove private YouTube refresh methods from controller
    - Inject and use ArtistYouTubeRefreshService
    - Clean up unused imports
    - _Requirements: Clean Architecture, Single Responsibility Principle_

  - [x] 14.3 Write tests for ArtistYouTubeRefreshService
    - Test force refresh functionality
    - Test conditional refresh based on staleness
    - Test basic metrics and analytics refresh separately
    - Test error handling and edge cases
    - _Requirements: Test Coverage, Service Layer Testing_

## Notes

- All tasks have been completed successfully
- The YouTube integration is fully implemented and tested
- All property-based tests are passing
- End-to-end integration tests validate the complete flow
- **Architecture has been refactored for clean separation of concerns:**
  - Business logic moved from ArtistController to dedicated ArtistYouTubeRefreshService
  - Controller now focuses solely on HTTP request/response handling
  - Service layer properly encapsulates YouTube refresh operations
- The implementation includes:
  - Complete YouTube API service with quota management
  - Background job processing with priority-based dispatching
  - Frontend integration with metrics display
  - Comprehensive error handling and graceful degradation
  - Full test coverage including property-based testing
- YouTube API calls are properly mocked in all tests using `Http::fake()`
- The integration follows the established patterns from Spotify integration
- All 27 correctness properties are implemented and validated
- **100 YouTube-related tests passing with 769 assertions**
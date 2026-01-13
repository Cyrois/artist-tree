# Implementation Plan: VEVO Channel Detection

## Overview

This implementation plan converts the VEVO channel detection design into discrete coding tasks. The system will identify VEVO YouTube channels during artist searches and automatically replace them with the artist's primary channel (highest subscriber count). All tasks build incrementally and include property-based testing for correctness validation.

## Tasks

- [x] 1. Database schema updates and migrations
  - Create migration for artist_links table additions (review_status and vevo_checked_at columns)
  - Update ArtistLink model to include new fillable fields
  - Add relationships and accessors for VEVO detection functionality
  - _Requirements: 5.1, 5.3_

- [x]* 1.1 Write property test for database schema
  - **Property 12: Pending Approval Status Assignment**
  - **Validates: Requirements 5.3**

- [x] 2. Extend YouTubeChannelDTO for VEVO detection
  - Add isVerified, hasRecentActivity, lastUploadDate, and description properties
  - Implement factory methods for creating DTOs from YouTube API responses
  - Add validation methods for channel data integrity
  - _Requirements: 1.1, 1.2, 1.3, 9.2_

- [x]* 2.1 Write property tests for YouTubeChannelDTO extensions
  - **Property 17: Replacement Quality Validation**
  - **Property 18: Recent Activity Validation**
  - **Validates: Requirements 9.1, 9.2**

- [x] 3. Implement VEVOChannelDetectionService
  - Create service class with isVEVOChannel method for pattern detection
  - Implement shouldCheckArtist and markArtistAsChecked methods
  - Add detectVEVOChannelsForArtist method with caching logic
  - Include VEVO pattern matching for names, descriptions, and video counts
  - _Requirements: 1.1, 1.2, 1.3, 6.1_

- [x] 3.1 Write property tests for VEVO detection patterns
  - **Property 1: VEVO Channel Name Detection**
  - **Property 2: Zero Video VEVO Detection**
  - **Property 3: VEVO Description Pattern Detection**
  - **Validates: Requirements 1.1, 1.2, 1.3**

- [x] 3.2 Write property test for search-triggered detection
  - **Property 13: Search-Triggered VEVO Detection**
  - **Validates: Requirements 6.1**

- [x] 4. Implement YouTubeChannelSearchService
  - Create service for searching YouTube channels by artist name and aliases
  - Implement searchChannelsForArtist with API integration
  - Add filterVEVOChannels method to exclude VEVO results from candidates
  - Include quota management and error handling for search API calls
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 4.1 Write property tests for channel search functionality
  - **Property 4: Artist Name Search Query**
  - **Property 5: Artist Alias Search Coverage**
  - **Property 6: VEVO Channel Exclusion from Candidates**
  - **Validates: Requirements 2.1, 2.2, 2.3**

- [x] 5. Implement YouTubeChannelRankingAlgorithm
  - Create ranking service with calculateChannelScore method
  - Implement subscriber count prioritization and verification bonuses
  - Add selectBestChannel method with tie-breaking logic
  - Include minimum subscriber threshold validation (1000+ subscribers)
  - _Requirements: 3.1, 3.2, 3.6, 4.5_

- [x] 5.1 Write property tests for channel ranking logic
  - **Property 7: Subscriber Count Ranking Priority**
  - **Property 8: Verified Channel Ranking Bonus**
  - **Property 9: Verified Channel Tie-Breaking**
  - **Property 11: Minimum Subscriber Threshold**
  - **Validates: Requirements 3.1, 3.2, 3.6, 4.5**

- [x] 6. Checkpoint - Core services implementation complete
  - Ensure all detection, search, and ranking services are working
  - Verify property tests are passing for core logic
  - Ask the user if questions arise

- [x] 7. Implement VEVOChannelReplacementJob
  - Create background job class with handle method for processing individual artists
  - Integrate all services (detection, search, ranking) in job workflow
  - Implement database update logic for channel replacement
  - Add error handling with exponential backoff and retry logic
  - _Requirements: 4.1, 8.1, 8.6_

- [x] 7.1 Write property tests for replacement job logic
  - **Property 10: Database Update on Replacement**
  - **Property 15: API Error Retry Logic**
  - **Property 16: Error Isolation in Processing**
  - **Validates: Requirements 4.1, 8.1, 8.6**

- [x] 8. Integrate VEVO detection with artist search flow
  - Update ArtistController search method to trigger VEVO detection jobs
  - Modify search response to respect shouldCheckArtist logic
  - Ensure search performance is not impacted by async job dispatch
  - Add queue configuration for vevo-detection queue
  - _Requirements: 6.1, 7.1_

- [x]* 8.1 Write property test for API response updates
  - **Property 14: API Response Channel Update**
  - **Validates: Requirements 7.1**

- [x] 9. Update existing YouTube integration points
  - Extend YouTubeService to support channel search API calls
  - Update existing channel data fetching to include verification status
  - Ensure compatibility with existing YouTube metrics and caching
  - Add new API endpoints if needed for channel search functionality
  - _Requirements: 2.1, 2.2, 7.1_
  - _Note: YouTubeChannelSearchService already integrates with YouTubeService for channel details_

- [x]* 9.1 Write unit tests for YouTube API integration
  - Test API request formatting and response parsing
  - Test error handling for search and channel APIs
  - Test quota management integration
  - _Note: Covered by existing YouTubeChannelSearchServiceTest_

- [x] 10. Configuration and environment setup
  - Add configuration options for VEVO detection patterns
  - Set up queue configuration for background job processing
  - Add environment variables for detection thresholds and timeouts
  - Configure caching settings for detection results
  - _Requirements: 6.1, 8.1_

- [x] 11. Final integration and testing
  - Wire all components together in service container
  - Test end-to-end flow from search to channel replacement
  - Verify database updates and API response consistency
  - Ensure proper error handling and graceful degradation
  - _Requirements: 4.1, 7.1, 8.6_

- [x]* 11.1 Write integration tests for complete flow
  - Test search → detection → replacement → API response cycle
  - Test error scenarios and recovery mechanisms
  - Test concurrent processing and race conditions
  - _Note: Covered by VEVOChannelReplacementJobTest_

- [x] 12. Final checkpoint - Complete system verification
  - Ensure all tests pass and system works end-to-end
  - Verify VEVO detection accuracy and replacement quality
  - Ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design
- Unit tests validate specific examples and integration points
- Background jobs use Laravel's queue system for async processing
- YouTube API integration reuses existing service patterns from the codebase
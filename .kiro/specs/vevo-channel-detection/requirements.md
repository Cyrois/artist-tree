# Requirements Document

## Introduction

This document outlines the requirements for implementing VEVO YouTube channel detection and automatic replacement with primary artist channels. The system will identify artists currently linked to VEVO channels (which typically have no videos and redirect users) and automatically promote the artist's main YouTube channel with the highest subscriber count as the primary channel ID.

## Glossary

- **VEVO_Channel**: YouTube channels owned by VEVO that typically redirect to artist's main channels and have no videos
- **Primary_Channel**: The main YouTube channel for an artist, typically with the most subscribers and active content
- **VEVOChannelDetectionService**: Service responsible for identifying VEVO channels and finding alternative channels
- **YouTubeChannelSearchService**: Service for searching and discovering alternative YouTube channels for artists
- **VEVOChannelReplacementJob**: Background job that processes individual VEVO channel detection and replacement when triggered by search
- **YouTubeChannelRankingAlgorithm**: Algorithm that selects the best alternative channel based on subscriber count and other criteria

## Requirements

### Requirement 1: VEVO Channel Detection

**User Story:** As a system administrator, I want to automatically detect VEVO YouTube channels, so that I can identify artists that need their primary channel updated.

#### Acceptance Criteria

1. THE VEVOChannelDetectionService SHALL identify VEVO channels by checking if the channel name contains "VEVO" (case-insensitive)
2. THE VEVOChannelDetectionService SHALL identify VEVO channels by checking if the channel has zero videos
3. THE VEVOChannelDetectionService SHALL identify VEVO channels by checking if the channel description mentions VEVO
4. WHEN a channel is identified as VEVO, THE system SHALL mark it for replacement processing
5. THE system SHALL maintain a list of known VEVO channel patterns for accurate detection
6. THE system SHALL log all VEVO channel detections for audit purposes

### Requirement 2: Alternative Channel Discovery

**User Story:** As a system, I want to find alternative YouTube channels for artists with VEVO channels, so that I can identify the best primary channel candidate.

#### Acceptance Criteria

1. THE YouTubeChannelSearchService SHALL search for channels using the artist's name as the search query
2. THE YouTubeChannelSearchService SHALL search for channels using the artist's aliases as additional search queries
3. WHEN searching for channels, THE system SHALL filter results to exclude VEVO channels from candidates
4. THE system SHALL collect subscriber count, video count, and channel verification status for each candidate
5. THE system SHALL validate that candidate channels actually belong to the artist by checking channel content
6. THE system SHALL limit search results to a maximum of 10 candidate channels per artist to manage API quota

### Requirement 3: Channel Ranking and Selection

**User Story:** As a system, I want to automatically select the best YouTube channel for each artist, so that the primary channel represents their most active and popular presence.

#### Acceptance Criteria

1. THE YouTubeChannelRankingAlgorithm SHALL rank candidate channels primarily by subscriber count (highest first)
2. THE YouTubeChannelRankingAlgorithm SHALL give bonus weight to verified channels in the ranking
3. THE YouTubeChannelRankingAlgorithm SHALL give bonus weight to channels with recent video activity (uploaded within 6 months)
4. THE YouTubeChannelRankingAlgorithm SHALL exclude channels with zero subscribers from consideration
5. THE YouTubeChannelRankingAlgorithm SHALL exclude channels that appear to be fan channels or unofficial accounts
6. WHEN multiple channels have similar subscriber counts (within 10%), THE system SHALL prefer the verified channel

### Requirement 4: Automatic Channel Replacement

**User Story:** As a system administrator, I want VEVO channels to be automatically replaced with the best alternative, so that artist data reflects their actual YouTube presence.

#### Acceptance Criteria

1. WHEN a better alternative channel is found, THE system SHALL update the artist's youtube_channel_id in the database
2. THE system SHALL preserve the original VEVO channel ID in an audit log before replacement
3. THE system SHALL trigger a YouTube metrics refresh for the new channel immediately after replacement
4. THE system SHALL record the replacement timestamp and reason in the artist's metadata
5. THE system SHALL only perform automatic replacement if the alternative channel has at least 1000 subscribers
6. THE system SHALL send notifications to administrators about channel replacements for review

### Requirement 5: Database Support for Future Review System

**User Story:** As a system administrator, I want the database to support future manual review functionality, so that channel replacements can be tracked and reviewed when needed.

#### Acceptance Criteria

1. THE artist_links table SHALL include a review_status column to track the source and approval status of channel links
2. THE review_status column SHALL support values: "public_added", "admin_added", "pending_approval", "approved"
3. WHEN the system automatically replaces a VEVO channel, THE new link SHALL be marked as "pending_approval"
4. THE system SHALL maintain an audit trail of all channel replacements in the database
5. THE system SHALL preserve the original VEVO channel information for future reference
6. THE database schema SHALL support future implementation of manual review workflows

### Requirement 6: Search-Triggered Processing

**User Story:** As a system, I want to detect and replace VEVO channels when users search for artists, so that processing only happens for artists that are actively being viewed.

#### Acceptance Criteria

1. WHEN an artist appears in search results, THE system SHALL check if the artist has a VEVO channel
2. WHEN a VEVO channel is detected during search, THE system SHALL asynchronously trigger the replacement process
3. THE system SHALL process VEVO detection and replacement without blocking the search response
4. THE system SHALL cache detection results to avoid repeated processing for the same artist
5. THE system SHALL prioritize recently searched artists for VEVO detection processing
6. THE system SHALL limit concurrent VEVO detection processes to manage YouTube API quota usage

### Requirement 7: API Integration and Data Consistency

**User Story:** As a developer, I want channel replacements to be reflected in all API responses, so that frontend applications show the correct YouTube data.

#### Acceptance Criteria

1. WHEN an artist's channel is replaced, THE API SHALL immediately return data for the new channel
2. THE system SHALL invalidate cached YouTube metrics for the old channel
3. THE system SHALL fetch fresh metrics for the new channel within 24 hours of replacement
4. THE API SHALL include replacement metadata in artist responses for transparency
5. THE system SHALL ensure all related artist data (metrics, links) is updated consistently
6. THE system SHALL handle concurrent requests gracefully during channel replacement operations

### Requirement 8: Error Handling and Resilience

**User Story:** As a system administrator, I want the VEVO detection system to handle errors gracefully, so that processing continues even when individual operations fail.

#### Acceptance Criteria

1. WHEN YouTube API returns errors, THE system SHALL retry with exponential backoff up to 3 times
2. WHEN channel search returns no results, THE system SHALL log the issue and continue with the next artist
3. WHEN multiple API errors occur, THE system SHALL pause processing and alert administrators
4. THE system SHALL handle cases where the proposed replacement channel becomes unavailable
5. THE system SHALL provide rollback functionality to restore original channel IDs if needed
6. THE system SHALL continue processing other artists even if individual replacements fail

### Requirement 9: Data Validation and Quality Assurance

**User Story:** As a data analyst, I want channel replacements to improve data quality, so that artist metrics are more accurate and representative.

#### Acceptance Criteria

1. THE system SHALL validate that replacement channels have significantly more subscribers than VEVO channels
2. THE system SHALL verify that replacement channels have active video content (uploaded within 12 months)
3. THE system SHALL check that replacement channels match the artist's genre and content style
4. THE system SHALL prevent replacement with channels that appear to be covers, tributes, or fan accounts
5. THE system SHALL ensure replacement channels are in the same language/region as the artist when possible
6. THE system SHALL validate channel ownership through cross-referencing with other social media links when available
# Entity Resolution & Social Discovery System

## 1. Problem Statement
We need to link Spotify artists to their external social media accounts (Instagram, YouTube, SoundCloud, Twitter).
**Challenge:** Naming is inconsistent (e.g., "The Strokes" on Spotify vs. "@thestrokesofficial" on Instagram).
**Solution:** A **Human-in-the-Loop (HITL)** architecture where an automated bot generates "suggestions" and the community verifies them.

## 2. Database Architecture

### A. The "Truth" Table (`artist_social_links`)
Stores the currently active, verified links served to the frontend.
*   **Columns:**
    *   `id`
    *   `artist_id` (FK)
    *   `platform` (string: 'instagram', 'youtube', etc.)
    *   `url` (string, nullable)
    *   `status` (enum: 'verified', 'suggested', 'does_not_exist', 'flagged')
    *   `report_count` (int: default 0)
    *   `created_at`
    *   `updated_at`
*   **Constraint:** Unique `[artist_id, platform]`.
*   **Note:** If `status` is 'does_not_exist', the bot knows to stop searching for this specific platform.

### B. The "Staging" Table (`artist_social_suggestions`)
Stores unverified guesses found by the bot or submitted by users.
*   **Columns:**
    *   `id`
    *   `artist_id` (FK)
    *   `platform` (string)
    *   `suggested_identifier` (string: URL or handle)
    *   `source` (string: 'musicbrainz', 'youtube_scrape', 'user_submission')
    *   `confidence_score` (int: 0-100)
    *   `vote_count` (int: default 0) **(Optimization: Counter Cache)**
    *   `status` (enum: 'pending', 'approved', 'rejected')
*   **Constraint:** Unique `[artist_id, platform, suggested_identifier]`.

### C. The "Audit" Table (`suggestion_votes`)
Tracks who voted to prevent spam and duplicate voting.
*   **Columns:**
    *   `id`
    *   `suggestion_id` (FK)
    *   `user_id` (FK)
    *   `vote_type` (int: 1 for confirm, -1 for reject)
*   **Constraint:** Unique `[user_id, suggestion_id]`.

## 3. The Discovery Bot (Logic Flow)

The bot runs as a background job (`FindArtistSocialsJob`).

1.  **Guard Clause:** Check `artist_social_links`.
    *   If a platform exists and is 'verified' OR 'does_not_exist', **SKIP** searching for that platform.
2.  **Source 1: MusicBrainz (High Trust)**
    *   Query MusicBrainz for URL relationships.
    *   Confidence: 80-90.
3.  **Source 2: YouTube Chain-of-Trust (High Trust)**
    *   Search YouTube for "Artist Name".
    *   Find **Verified** channel.
    *   Scrape channel description/bio for Instagram/Twitter links.
    *   Confidence: 90-99.
4.  **Source 3: Fuzzy Search (Low Trust)**
    *   Fallback search if others fail.
    *   Confidence: <50.

## 4. The Verification System (Community Consensus)

1.  **Voting:** Authenticated users vote on pending suggestions.
2.  **Counter Cache:** When a vote is cast, increment `artist_social_suggestions.vote_count`.
3.  **Weighted Voting:**
    *   **Admin:** 5 points (Instant verify).
    *   **Standard User:** 1 point.
4.  **Threshold:**
    *   If `vote_count >= 5`: **Auto-Promote**.
    *   Move data to `artist_social_links`.
    *   Mark suggestion as `approved`.
    *   Delete other pending suggestions for that platform.

## 5. Maintenance & Optimization

*   **Performance:**
    *   Frontend queries only `artist_social_links` (clean table, no joins).
    *   Backend jobs query `artist_social_suggestions` (using indexed `vote_count`).
*   **Garbage Collection:**
    *   Nightly job deletes `rejected` suggestions > 7 days old.
    *   Deletes `pending` suggestions > 30 days old with 0 votes (stale data).

## 6. Trust & Safety (Anti-Abuse)

### A. Bad Actor Prevention
*   **Submission Rate Limits:** Max 5 suggestions per hour per user.
*   **Rejection Penalty:** If a user's suggestions are marked `rejected` 3 times, suspend suggestion privileges.

### B. Reversion Mechanism (Reporting)
If a bad link is verified (false positive):
1.  Users can **Report** a verified link.
2.  If `report_count >= 3`:
    *   Status changes to `flagged`.
    *   Link is hidden from public view.
    *   Requires Admin review to restore or delete.

## 7. Implementation Notes for Next Agent

To continue this work, please implement the following:
1.  **Migrations:** Create the 3 tables defined in Section 2.
2.  **Models:** Create `ArtistSocialLink`, `ArtistSocialSuggestion`, `SuggestionVote` with proper relationships.
3.  **Job:** Create `FindArtistSocialsJob` implementing the logic in Section 3.
4.  **Controller:** Create `SuggestionVoteController` to handle the voting and auto-promotion logic.
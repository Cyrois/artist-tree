# Artist-Tree Project Guidelines (Gemini)

## Project Overview
Artist-Tree is a festival lineup builder and artist discovery platform that helps music festival organizers create balanced lineups using data-driven insights from Spotify and YouTube APIs.

**Tech Stack:** Laravel 12 + Inertia.js v2 + Vue 3 + Laravel Cloud (PostgreSQL)

## Documentation Structure

This project uses modular documentation:

- **GEMINI.md** (this file): Business domain rules, architecture, and project-specific requirements
- **Agent Instructions** (`.gemini/agents/`): Specialized AI agent roles and responsibilities
- **Framework Guidelines** (`.gemini/guidelines/`): Implementation patterns and framework-specific rules

### Quick Navigation
- Laravel/PHP patterns: See `.gemini/guidelines/backend-patterns.md`
- Vue/Inertia/Tailwind patterns: See `.gemini/guidelines/frontend-patterns.md`
- Testing with Pest: See `.gemini/guidelines/testing-patterns.md`
- Development workflow: See `.gemini/guidelines/workflow.md`
- Complete Laravel Boost Guidelines: See `.gemini/guidelines/laravel-boost.md`

For detailed navigation, see `.gemini/README.md`.

---

## Communication & Planning Style

When presenting implementation plans:
- **Provide high-level summaries only** - focus on the main steps without implementation details
- Keep plan overviews concise and action-oriented
- Avoid verbose explanations of "how" unless specifically asked
- Let the user ask for details when needed

---

## Architecture Decision ✅ CONFIRMED

**Hybrid Architecture: Inertia.js + RESTful API (Option C)**

### How It Works
- **Page Navigation:** Inertia.js handles routing and server-side rendering of Vue pages
- **Interactive Features:** RESTful API endpoints handle dynamic operations (search, tier calculations, artist management)
- **Authentication:** Laravel Sanctum with cookie-based auth for Inertia frontend
- **Deployment:** Single deployment to Laravel Cloud (monolithic app)

    ├── GET /api/artists/search?q=... → JSON (autocomplete)
    ├── GET /api/artists/{id} → JSON (artist details by database ID)
    ├── GET /api/artists?spotify_id=... → JSON (artist details by Spotify ID)
    ├── POST /api/artists/select → JSON (select artist for lineup/etc.)
    ├── GET /api/artists/{id}/top-tracks → JSON (artist's top tracks)
    ├── GET /api/artists/{id}/albums → JSON (artist's albums)
    ├── POST /api/artists/{id}/refresh → JSON (refresh metrics)
    ├── POST /api/lineups → JSON (create lineup)
    ├── POST /api/lineups/{id}/artists → JSON (add artist, recalculate tiers)
    └── DELETE /api/lineups/{id}/artists/{artistId} → JSON

### Frontend Pattern
- Use Inertia for page loads and navigation
- Use axios for API calls within Vue components for interactivity
- Example: Search autocomplete, drag-and-drop lineup builder, tier recalculation

### Future Public API
- API is designed with RESTful standards from day one
- Can be exposed publicly later by adding API token authentication
- No major refactoring needed to go public

---

## Domain-Specific Rules

### Artist Scoring Algorithm
**Business Rule:** Score artists objectively using organization-specific, extensible metric weights

**Technical Requirements:**
- Score range: 0-100 (normalized)
- Algorithm must be **deterministic** (same input + same weights = same output)
- **Weights stored in `metric_weights` table** (one row per metric per organization)
- Supports adding new metrics (Instagram, TikTok, etc.) without code changes

**Metric Weights Table Schema:**
```sql
metric_weights:
- id (bigint unsigned, auto-increment)
- organization_id (foreign key)
- metric_name (varchar 100) -- e.g., 'spotify_monthly_listeners'
- weight (decimal 3,2) -- 0.00 to 1.00
- enabled (boolean, default true)
- created_at, updated_at

UNIQUE(organization_id, metric_name)
Validation: SUM(weight) WHERE enabled=true must equal 1.00
```

**Available Metrics (MVP Launch):**
- `spotify_monthly_listeners` - Artist's monthly listener count on Spotify
- `spotify_popularity` - Spotify's proprietary popularity score (0-100)
- `youtube_subscribers` - YouTube channel subscriber count

**Future Metrics (Add via INSERT when ready):**
- `engagement_rate` - Calculate from likes/comments/followers ratio
- `instagram_followers`
- `tiktok_followers`
- `apple_music_listeners`
- `soundcloud_plays`
- `bandcamp_sales`

**Default Metric Weight Presets** (`config/artist-tree.php`):
```php
'metric_presets' => [
    'balanced' => [
        'spotify_monthly_listeners' => 0.40,  // Evenly weighted
        'spotify_popularity' => 0.30,
        'youtube_subscribers' => 0.30,
    ],
    'streaming_focused' => [
        'spotify_monthly_listeners' => 0.55,  // Heavy Spotify bias
        'spotify_popularity' => 0.30,
        'youtube_subscribers' => 0.15,
    ],
    'social_media_focused' => [
        'spotify_monthly_listeners' => 0.20,  // Heavy YouTube bias
        'spotify_popularity' => 0.15,
        'youtube_subscribers' => 0.65,
    ],
];

// Note: engagement_rate removed from MVP presets
// When added later, weights will need rebalancing
```

**Organization Metric Management:**
- New organizations auto-populate with "balanced" preset metrics
- Owner/Admin can enable/disable metrics
- Owner/Admin can adjust weights (must sum to 1.00)
- Disabled metrics don't contribute to score calculation
- Easy to add new metrics: INSERT new rows with weight

**Normalization Strategy: Logarithmic Scale**
- Use **logarithmic normalization** to handle massive range (100 followers → 100M followers)
- Formula: `normalized_score = (log10(value + 1) / log10(max_expected)) * 100`
- Max expected values (configurable):
  - `spotify_monthly_listeners`: 100,000,000 (100M)
  - `spotify_popularity`: 100 (already 0-100, no normalization needed)
  - `youtube_subscribers`: 50,000,000 (50M)
- Rationale: Industry standard, handles unknowns and superstars fairly, diminishing returns at scale

**Service class:** `App\Services\ArtistScoringService`
```php
public function calculateScore(Artist $artist, Organization $organization): float
{
    $metrics = MetricWeight::where('organization_id', $organization->id)
        ->where('enabled', true)
        ->get();

    $score = 0;
    foreach ($metrics as $metric) {
        $value = $this->getMetricValue($artist, $metric->metric_name);
        $normalized = $this->normalizeLogarithmic($value, $metric->metric_name);
        $score += $normalized * $metric->weight;
    }

    return round($score, 2); // 0-100
}

private function normalizeLogarithmic(float $value, string $metricName): float
{
    $maxValues = config('artist-tree.normalization_max');
    $max = $maxValues[$metricName] ?? 100000000;

    // Special case: spotify_popularity already 0-100
    if ($metricName === 'spotify_popularity') {
        return $value;
    }

    // Logarithmic normalization
    return (log10($value + 1) / log10($max)) * 100;
}
```

**Handling Missing Data:**
- If artist missing data for an **enabled** metric → Treat as **zero (0)**
- This heavily penalizes score for that metric's weight
- Example: YouTube disabled by org → Artist not penalized for missing YouTube
- Example: YouTube enabled by org, artist has no channel → Gets 0 for YouTube portion of score
- Rationale: Keeps calculation simple, incentivizes artists to have presence on all platforms org cares about

**MUST have unit tests** with edge cases:
- Mega-stars (>10M followers)
- Unsigned artists (<1000 followers)
- Missing data scenarios (no YouTube channel → treated as 0)
- Zero values vs NULL values
- Different organization weights produce different scores
- Disabled metrics excluded from calculation entirely
- Artist with all NULL values returns score of 0

### Tier Classification
**Business Rule:** Suggest tier placement based on artist scores relative to lineup averages

**Technical Requirements:**
- Tiers: `headliner`, `sub_headliner`, `mid_tier`, `undercard`
- **Frontend-only Logic:** Tier suggestion happens in the UI when adding an artist
- **No Backend Recalculation:** We do not store or recalculate suggested tiers in the database
- **Storage:** Only the selected `tier` is stored in `lineup_artists`

**Database Schema (lineup_artists):**
```sql
- tier (enum) -- current tier
```

**Suggested Tier Calculation Logic (Frontend):**
1. Calculate the **Average Score** for each existing tier in the lineup.
   - Example: Headliners Avg = 85, Sub-Headliners Avg = 70, etc.
2. Compare the new artist's score to these averages.
3. Suggest the tier whose average is closest to the new artist's score.
   - *Empty Lineup Case:* If lineup is empty, fallback to static thresholds (e.g., >80 Headliner).

**Add Artist Flow:**
1. User opens "Add to Lineup" modal.
2. Frontend calculates the suggestion on the fly.
3. The suggested tier is **pre-selected** in the radio button list.
4. User can accept it or select a different tier manually.
5. The final selection is sent to the API and stored as `tier`.

### External API Integration

#### Spotify API
**Service:** `App\Services\SpotifyService`

**Rules:**
- Cache OAuth access tokens for 3600 seconds (1 hour)
- Cache artist search results for 24 hours
- Implement exponential backoff for rate limit errors (429)
- **Never call Spotify API directly from controllers** - always use service
- Rate limit: 180 requests per minute (enforce in service)
- Required data: artist name, Spotify ID, followers, popularity, genres, image URL

**Configuration:**
```php
// config/services.php
'spotify' => [
    'client_id' => env('SPOTIFY_CLIENT_ID'),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
],
```

**Artist Auto-Creation from Spotify:**
- Artists returned from Spotify search are considered verified/real artists
- When search returns Spotify results not in local database:
  1. Return search results immediately to frontend (don't block)
  2. Dispatch background job to create missing artists in database
  3. Job creates artist record + initial metrics from Spotify data
- This ensures artists are available locally for future searches
- Job class: `App\Jobs\CreateArtistsFromSpotifyJob`
- Job must be idempotent: Use atomic `firstOrCreate` or `updateOrCreate` to prevent race conditions (never check existence then create in separate steps)
- Use `spotify_id` as unique identifier to prevent duplicates

#### YouTube API
**Service:** `App\Services\YouTubeService`

**Rules:**
- Daily quota: 10,000 units - monitor with `youtube_quota_usage:YYYY-MM-DD` cache key
- Cache channel searches for 24 hours
- **Graceful fallback** if artist has no YouTube channel (don't fail)
- Search by artist name to find channel
- Fetch subscriber count and video count
- Store channel ID in `artists.youtube_channel_id`

**Configuration:**
```php
// config/services.php
'youtube' => [
    'api_key' => env('YOUTUBE_API_KEY'),
],
```

### Organizations & Multi-Tenancy

**Business Rule:** Users belong to organizations that share scoring weights and lineups

**Organization Structure:**
- **On Signup:** Auto-create personal organization for new users (e.g., "Calvin's Organization")
- **Initial Role:** User is set as "owner" of their auto-created organization
- **Database Design:** Many-to-many relationship (users can join multiple organizations)
- **UX (MVP):** Simple single-org experience (multi-org switching added later)

**Roles & Permissions:**
- **Owner:** Full control - manage billing, invite users, edit weights, manage all lineups, delete org
- **Admin:** Manage settings - edit scoring weights, invite members, manage lineups
- **Member:** Create/view lineups using org's scoring weights (read-only on weights)

**Organization Isolation:**
- Lineups belong to organizations (not individual users)
- Artist scores calculated using organization's weights
- Users can only see lineups from their current organization(s)

**Service classes:**
- `App\Services\OrganizationService` - Org creation, member management
- Authorization via Laravel Policies: `OrganizationPolicy`, `LineupPolicy`

### Database Schema Rules

**Primary Key Strategy:** Auto-increment IDs (standard Laravel)
- Simpler queries, better performance
- Smaller indexes
- Use `$table->id()` in migrations

**Required Tables:**
- `organizations` - Organization/team data
- `organization_user` - Pivot table (user_id, organization_id, role)
- `metric_weights` - Per-org metric weights (extensible for new platforms)
- `users` - User accounts (Laravel default)
- `artists` - Artist master data
- `artist_metrics` - Historical metrics snapshots (time-series data)
- `lineups` - Organization-owned lineups
- `lineup_artists` - Pivot table with tier assignment and override tracking

**Indexes:**
- `organizations.id` (primary)
- `organization_user` (composite: user_id + organization_id, unique)
- `organization_user.role` (for permission checks)
- `metric_weights` (composite: organization_id + metric_name, unique)
- `metric_weights.enabled` (for filtering active metrics)
- `users.id` (primary, Laravel default)
- `artists.spotify_id` (unique)
- `artists.name` (full-text search)
- `artist_metrics.artist_id` (foreign key)
- `artist_metrics.score` (for sorting)
- `lineups.organization_id` (foreign key)
- `lineup_artists` (composite unique: lineup_id + artist_id)
- `lineup_artists.tier_override` (for filtering manual vs auto placements)

**JSON Columns:**
- `artists.genre` (array of strings)
- Cast as array in Eloquent model

### Search Implementation

**Technology:** Laravel Scout with database driver (upgrade to Meilisearch later if needed)

**Rules:**
- Artist search endpoint: `GET /api/artists/search?q={query}`
- Support **partial matching** on artist names
- Return maximum **20 results** for autocomplete
- Response time target: <500ms
- **Debounce frontend input** (300ms minimum) to reduce API calls
- Search across: artist name, genres (if user enables genre search)

**Search Response Structure:**
```json
{
  "id": 123,              // Database ID (null if not yet in DB)
  "spotify_id": "abc123", // Spotify ID (always present)
  "name": "Artist Name",
  "genres": ["rock", "indie"],
  "image_url": "https://...",
  "exists_in_database": true,
  "source": "local"       // "local" or "spotify"
}
```

**Artist Details Lookup:**
- Frontend uses `id` if available: `GET /api/artists/{id}`
- Frontend uses `spotify_id` as fallback: `GET /api/artists?spotify_id={spotifyId}`
- Both return the same response structure (full artist details with metrics)

```vue
<script setup>
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

const searchQuery = ref('')
const results = ref([])

const searchArtists = useDebounceFn(async (query) => {
  if (!query || query.length < 2) return
  const response = await axios.get('/api/artists/search', { params: { q: query } })
  results.value = response.data.data
}, 300)

watch(searchQuery, (newValue) => searchArtists(newValue))
</script>

---

## Code Organization

```
app/
├── Services/              # Business logic, external APIs
│   ├── SpotifyService.php           # Spotify API integration
│   ├── YouTubeService.php           # YouTube API integration
│   ├── ArtistSearchService.php      # Artist discovery/creation
│   ├── ArtistScoringService.php     # Score calculation algorithm (org-aware)
│   └── OrganizationService.php      # Org creation, member management
│
├── Models/
│   ├── Organization.php
│   ├── MetricWeight.php             # Org-specific metric weights
│   ├── User.php                     # Laravel default + org relationship
│   ├── Artist.php
│   ├── ArtistMetric.php             # Time-series metrics data
│   ├── Lineup.php                   # Belongs to organization
│   └── LineupArtist.php             # Pivot model with tier override
│
├── Http/
│   ├── Controllers/
│   │   ├── OrganizationController.php    # Org settings, weights (Inertia)
│   │   ├── ArtistController.php          # API endpoints (thin)
│   │   ├── LineupController.php          # API endpoints (thin)
│   │   └── DashboardController.php       # Inertia page
│   │
│   ├── Resources/         # API response transformers
│   │   ├── OrganizationResource.php
│   │   ├── UserResource.php
│   │   ├── ArtistResource.php
│   │   ├── ArtistMetricResource.php
│   │   └── LineupResource.php
│   │
│   └── Requests/          # Form validation
│       ├── StoreLineupRequest.php
│       ├── AddArtistToLineupRequest.php
│       ├── UpdateScoringWeightsRequest.php
│       └── InviteMemberRequest.php
│
├── Policies/              # Authorization rules
│   ├── OrganizationPolicy.php       # Can user edit org weights?
│   └── LineupPolicy.php             # Can user edit lineup?
│
└── Jobs/                  # Async tasks
    ├── RefreshArtistMetricsJob.php
    └── FetchYouTubeDataJob.php
```

**For detailed Laravel implementation patterns** (service classes, controller patterns, external APIs, policies, etc.), see `.gemini/guidelines/backend-patterns.md`.

---

## Performance Requirements

**API Response Times:**
- Cached artist data: <500ms
- Artist search: <500ms
- External API calls: <2 seconds (with caching)

**Caching Strategy:**
- Spotify access token: 1 hour (Redis/file cache)
- Artist search results: 24 hours
- Artist metrics: 24 hours (force refresh via button)
- YouTube channel data: 24 hours

**Database Query Optimization:**
- Eager load relationships: `Lineup::with('artists.metrics')->find($id)`
- Paginate artist lists: 50 per page
- Index all foreign keys and search columns

---

## Security Rules

### API Keys & Credentials
- **NEVER expose API keys in frontend code or Git**
- Store in `.env`: `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `YOUTUBE_API_KEY`
- Add to `.env.example` with placeholder values
- Use `config('services.spotify.client_id')` - never `env()` outside config files

### Rate Limiting
- API endpoints: 60 requests/minute per authenticated user
- Public endpoints (if exposed): 20 requests/minute per IP
- Spotify/YouTube services: Respect their rate limits with backoff

### Input Validation
- Validate all user inputs in Form Request classes
- Artist names: max 255 chars, no HTML
- Lineup names: max 255 chars
- Prevent duplicate artists in same lineup (database unique constraint)

### Authorization
- Users can only view/edit their own lineups (Laravel Policy)
- API endpoints require authentication (`auth:sanctum` middleware)

---

## Testing Requirements

### Unit Tests (PHPUnit/Pest)
**REQUIRED for:**
- `ArtistScoringService` - test score calculation with various inputs
- Spotify/YouTube API response parsing

### Feature Tests
**REQUIRED for:**
- All API endpoints (CRUD operations)
- Artist search functionality
- Lineup creation and artist addition

### Mock External APIs
**NEVER hit real Spotify/YouTube APIs in tests** - always use `Http::fake()`.

**For detailed test examples, edge cases, factory patterns, and mocking strategies**, see `.gemini/guidelines/testing-patterns.md`.

---

## Prohibited Actions (Without User Approval)

- ❌ Changing default scoring weight presets in config file
- ❌ Modifying tier classification percentages
- ❌ Adding new external API integrations beyond Spotify/YouTube
- ❌ Changing database schema without migrations
- ❌ Removing or modifying existing tests
- ❌ Switching from Inertia to separate SPA architecture
- ❌ Using UUIDs instead of auto-increment IDs
- ❌ Installing additional dependencies
- ❌ Changing organization role permissions
- ❌ Removing auto-org creation on signup
- ❌ Committing or pushing changes to Git without explicit user instruction

---

## Development Workflow

See `.gemini/guidelines/workflow.md` for complete workflow, tooling, and quality checklist.

### Changelog Requirement (MANDATORY)

**All AI agents MUST update `CHANGELOG.md` after making significant changes.**

This ensures:
- Consistency across AI-assisted development sessions
- Clear documentation of what exists and how components integrate
- Prevention of duplicate or conflicting implementations

See `CHANGELOG.md` for the current state of the project and update it when:
- Adding new models, migrations, or database changes
- Creating new API endpoints or services
- Adding new Vue pages or components
- Making architectural changes

---

## Framework Implementation Guidelines

All framework-specific implementation guidelines have been moved to `.gemini/guidelines/`:

- **Laravel Boost Guidelines**: `.gemini/guidelines/laravel-boost.md`
- **Backend Patterns**: `.gemini/guidelines/backend-patterns.md`
- **Frontend Patterns**: `.gemini/guidelines/frontend-patterns.md`
- **Testing Patterns**: `.gemini/guidelines/testing-patterns.md`
- **Development Workflow**: `.gemini/guidelines/workflow.md`

AI agents and developers MUST consult these files for implementation.

---

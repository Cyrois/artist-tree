# Changelog

All notable changes to the Artist-Tree project are documented in this file.

This changelog tracks implementation progress and helps ensure AI assistants maintain consistency when making changes.

---

## Current State Summary

**Branch:** `spotify-integration` (merged into main via PR #6)

**Tech Stack:**
- Laravel 12 + Inertia.js v2 + Vue 3
- PostgreSQL database
- Tailwind CSS + shadcn/vue components

---

## [Unreleased]

### Infrastructure & Setup

#### Database Configuration
- **PostgreSQL migration** - Switched from MySQL to PostgreSQL for production compatibility with Laravel Cloud
  - Updated `config/database.php` to use PostgreSQL
  - Updated all references from MySQL to PostgreSQL
  - Files: `config/database.php`, `.env.example`

#### Project Initialization
- Laravel 12 application scaffolded with Breeze + Vue + Inertia
- Two-factor authentication columns added to users table
- Laravel Fortify configured for authentication

---

## Spotify Integration (Current Feature)

### Backend - API Layer

#### Models
- **`Artist` model** (`app/Models/Artist.php`)
  - Fields: `id`, `spotify_id` (unique), `name`, `genres` (JSON), `image_url`, timestamps, soft deletes
  - Scopes: `search($term)` - case-insensitive search (ILIKE for PostgreSQL, LIKE for SQLite/MySQL)
  - Scopes: `hasGenre($genre)` - filter by genre in JSON array
  - Relationships: `hasOne` to `ArtistMetric`
  - Methods: `hasStaleMetrics()` - checks if metrics older than 24 hours

- **`ArtistMetric` model** (`app/Models/ArtistMetric.php`)
  - Fields: `artist_id` (unique FK), `spotify_popularity` (0-100), `spotify_followers`, `youtube_subscribers`, `instagram_followers`, `tiktok_followers`, `refreshed_at`, timestamps
  - One-to-one relationship with Artist

#### Migrations
- **`2025_12_23_231643_create_artists_table.php`**
  - Creates `artists` table with GIN index for PostgreSQL jsonb genres column
  - Indexes: `spotify_id` (unique), `name`

- **`2025_12_23_231918_create_artist_metrics_table.php`**
  - Creates `artist_metrics` table
  - Future-proofed with `instagram_followers` and `tiktok_followers` columns

#### Services
- **`SpotifyService`** (`app/Services/SpotifyService.php`)
  - OAuth client credentials flow with token caching (1 hour TTL)
  - Methods:
    - `searchArtists(string $query, int $limit = 20)` - returns `SpotifyArtistDTO[]`
    - `getArtist(string $spotifyId)` - returns single `SpotifyArtistDTO`
  - Rate limiting: 180 requests/minute with per-minute counter
  - Search result caching: 24 hours
  - Retry logic: 3 retries with 1 second delay for 5xx errors
  - Config: `config/services.php` - `spotify.client_id`, `spotify.client_secret`

- **`ArtistSearchService`** (`app/Services/ArtistSearchService.php`)
  - Hybrid search: queries local database + Spotify API concurrently
  - Merges and deduplicates results by Spotify ID
  - Prioritizes local results over Spotify results
  - Methods:
    - `search(string $query, int $limit = 20)` - returns `Collection<ArtistSearchResultDTO>`
    - `getOrCreateFromSpotify(string $spotifyId)` - fetches or creates artist from Spotify
    - `refreshArtistFromSpotify(Artist $artist)` - updates artist data from Spotify
  - Dispatches `CreateArtistsFromSpotifyJob` for missing artists found in Spotify search

#### DTOs (Data Transfer Objects)
- **`SpotifyArtistDTO`** (`app/DataTransferObjects/SpotifyArtistDTO.php`)
  - Properties: `spotifyId`, `name`, `genres`, `imageUrl`, `popularity`, `followers`
  - Factory method: `fromSpotifyResponse(array $data)`

- **`ArtistSearchResultDTO`** (`app/DataTransferObjects/ArtistSearchResultDTO.php`)
  - Unified DTO for both local and Spotify search results
  - Properties: `spotifyId`, `name`, `genres`, `imageUrl`, `popularity`, `followers`, `existsInDatabase`, `databaseId`, `source`
  - Factory methods: `fromLocalArtist()`, `fromSpotifyArtist()`

#### Controllers
- **`ArtistController`** (`app/Http/Controllers/ArtistController.php`)
  - `GET /api/artists/search?q={query}&limit={limit}` - hybrid search
  - `GET /api/artists/{id}` - get artist by database ID
  - `GET /api/artists?spotify_id={spotifyId}` - get artist by Spotify ID
  - `POST /api/artists/select` - select artist (refreshes if stale)
  - `POST /api/artists/{id}/refresh` - force refresh from Spotify

#### API Resources
- **`ArtistResource`** (`app/Http/Resources/ArtistResource.php`)
  - Returns: `id`, `spotify_id`, `name`, `genres`, `image_url`, `metrics` (nested), `created_at`, `updated_at`

- **`ArtistSearchResultResource`** (`app/Http/Resources/ArtistSearchResultResource.php`)
  - Returns: `id`, `spotify_id`, `name`, `genres`, `image_url`, `exists_in_database`, `source`

#### Form Requests
- **`SearchArtistsRequest`** (`app/Http/Requests/SearchArtistsRequest.php`)
  - Validates: `q` (required, min:2, max:255), `limit` (optional, integer, 1-50)

- **`SelectArtistRequest`** (`app/Http/Requests/SelectArtistRequest.php`)
  - Validates: `artist_id` (required, integer, exists in artists table)

#### Jobs
- **`CreateArtistsFromSpotifyJob`** (`app/Jobs/CreateArtistsFromSpotifyJob.php`)
  - Background job to create artists found in Spotify search but not in local DB
  - Prevents duplicate creation (idempotent via Spotify ID check)

#### Exceptions
- **`SpotifyApiException`** (`app/Exceptions/SpotifyApiException.php`)
  - Custom exception for Spotify API errors
  - Factory method: `fromResponse()` - creates from HTTP response

#### Database Factories
- **`ArtistFactory`** (`database/factories/ArtistFactory.php`)
  - Generates test artists with realistic data

- **`ArtistMetricFactory`** (`database/factories/ArtistMetricFactory.php`)
  - States: `stale()` - creates metrics older than 24 hours
  - States: `fresh()` - creates recently refreshed metrics

### Frontend - Vue Components

#### Pages
- **`Search.vue`** (`resources/js/pages/Search.vue`)
  - Full artist search page with:
    - Debounced search input (300ms via `@vueuse/core`)
    - Real-time API calls to `/api/artists/search`
    - Loading, error, and empty states
    - Genre filtering (client-side)
    - Score range filtering (client-side)
    - Sort by score/name/listeners
    - "Trending Artists" section (mock data)
    - "Similar Artists" section (mock data)
  - Integrates with type-safe routes

- **`Artist/Show.vue`** (`resources/js/pages/Artist/Show.vue`)
  - Artist detail page
  - Fetches artist data via `GET /api/artists/{id}`
  - Displays: artist image, name
  - Loading, error, and not-found states
  - Back to search navigation

#### Components
- **`ArtistCard.vue`** (`resources/js/components/artist/ArtistCard.vue`)
  - Reusable artist card component
  - Displays: image, name, genres, metrics

- **`ArtistCardGrid.vue`** (`resources/js/components/artist/ArtistCardGrid.vue`)
  - Grid layout for artist cards
  - Configurable column count
  - Click handler for artist selection

- **`ArtistAvatar.vue`** (`resources/js/components/artist/ArtistAvatar.vue`)
  - Artist avatar component with fallback initials

#### Type-Safe Routes (Wayfinder)
- **`resources/js/routes/api/artists/index.ts`**
  - Generated type-safe route helpers:
    - `search.url({ query: { q: 'query' } })` - search endpoint
    - `show.url(artistId)` - show by database ID
    - `select.url()` - select endpoint
    - `refresh.url(artistId)` - refresh endpoint

### Routes

#### API Routes (`routes/api.php`)
```php
// Artist Search & Management API (auth:web + throttle:api)
GET  /api/artists/search       → ArtistController@search
GET  /api/artists/{id?}        → ArtistController@show
POST /api/artists/select       → ArtistController@select
POST /api/artists/{id}/refresh → ArtistController@refresh
```

#### Web Routes (`routes/web.php`)
```php
// Authenticated routes
GET /dashboard      → Inertia::render('Dashboard')
GET /search         → Inertia::render('Search')
GET /artist/{id}    → Inertia::render('Artist/Show')
GET /lineups        → Inertia::render('Lineups/Index')
GET /lineups/{id}   → Inertia::render('Lineups/Show')
```

### Tests

#### Feature Tests
- **`ArtistSearchApiTest.php`** (`tests/Feature/Api/ArtistSearchApiTest.php`)
  - 17 test cases covering:
    - Authentication requirements
    - Search validation (required, min length)
    - Local database search
    - Spotify API search (mocked)
    - Custom limit parameter
    - Rate limiting (60 requests/minute)
    - Artist selection with Spotify refresh
    - Artist selection without Spotify ID
    - Spotify API error handling
    - Artist refresh endpoint
    - Get artist by database ID
    - Get artist by Spotify ID
    - 404 handling for missing artists
    - Response structure validation

---

## UI/UX Components (Pre-existing)

### Layout Components
- **`MainLayout.vue`** - Main authenticated layout
- **`AppHeader.vue`** - Top navigation with breadcrumbs
- **`AppSidebar.vue`** - Side navigation
- **`AppShell.vue`** - Layout wrapper

### UI Components (shadcn/vue)
Located in `resources/js/components/ui/`:
- Button, Input, Badge, Card, DropdownMenu
- Dialog, Sheet, Tooltip
- Avatar, NavigationMenu
- And more...

### Mock Data (Development)
Located in `resources/js/data/`:
- `artists.ts` - Mock artist data with helper functions
- `constants.ts` - Genre list and other constants
- `types.ts` - TypeScript type definitions

---

## Internationalization (i18n)

### Translation Files
- **`lang/en.json`** - English translations
  - Namespaces: `common`, `artists`, `dashboard`, `lineups`, etc.
  - Artist-related keys:
    - `artists.search_page_title`
    - `artists.search_input_placeholder`
    - `artists.search_filters_button`
    - `artists.search_no_results_title`
    - `artists.show_back_button`
    - And more...

### Integration
- Uses `laravel-vue-i18n` package
- `trans()` function and `$t()` template helper

---

## Configuration

### Services Config (`config/services.php`)
```php
'spotify' => [
    'client_id' => env('SPOTIFY_CLIENT_ID'),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
],
'youtube' => [
    'api_key' => env('YOUTUBE_API_KEY'),
],
```

### Environment Variables (Required)
```env
SPOTIFY_CLIENT_ID=your_spotify_client_id
SPOTIFY_CLIENT_SECRET=your_spotify_client_secret
YOUTUBE_API_KEY=your_youtube_api_key  # Not yet implemented
```

---

## Not Yet Implemented (Per CLAUDE.md)

### Backend - Pending
- [ ] `YouTubeService` - YouTube API integration
- [ ] `ArtistScoringService` - Score calculation with org-specific weights
- [ ] `TierCalculationService` - Tier assignment algorithm
- [ ] `OrganizationService` - Organization management
- [ ] `Organization` model and migrations
- [ ] `MetricWeight` model (per-org scoring weights)
- [ ] `Lineup` and `LineupArtist` models
- [ ] `config/artist-tree.php` - Metric presets and normalization config
- [ ] Organization policies and authorization
- [ ] Lineup CRUD API endpoints

### Frontend - Pending
- [ ] Lineup builder page
- [ ] Drag-and-drop tier management
- [ ] Organization settings page
- [ ] Metric weight configuration UI
- [ ] Artist scoring display
- [ ] Real-time tier recalculation

---

## File Structure Reference

```
app/
├── DataTransferObjects/
│   ├── ArtistSearchResultDTO.php    ✅
│   └── SpotifyArtistDTO.php         ✅
├── Exceptions/
│   └── SpotifyApiException.php      ✅
├── Http/
│   ├── Controllers/
│   │   └── ArtistController.php     ✅
│   ├── Requests/
│   │   ├── SearchArtistsRequest.php ✅
│   │   └── SelectArtistRequest.php  ✅
│   └── Resources/
│       ├── ArtistResource.php       ✅
│       └── ArtistSearchResultResource.php ✅
├── Jobs/
│   └── CreateArtistsFromSpotifyJob.php ✅
├── Models/
│   ├── Artist.php                   ✅
│   ├── ArtistMetric.php             ✅
│   └── User.php                     ✅
└── Services/
    ├── ArtistSearchService.php      ✅
    └── SpotifyService.php           ✅

database/
├── factories/
│   ├── ArtistFactory.php            ✅
│   ├── ArtistMetricFactory.php      ✅
│   └── UserFactory.php              ✅
└── migrations/
    ├── 2025_12_23_231643_create_artists_table.php      ✅
    └── 2025_12_23_231918_create_artist_metrics_table.php ✅

resources/js/
├── components/
│   └── artist/
│       ├── ArtistAvatar.vue         ✅
│       ├── ArtistCard.vue           ✅
│       └── ArtistCardGrid.vue       ✅
├── pages/
│   ├── Artist/
│   │   └── Show.vue                 ✅
│   ├── Dashboard.vue                ✅
│   ├── Search.vue                   ✅
│   └── Settings.vue                 ✅
└── routes/
    └── api/
        └── artists/
            └── index.ts             ✅

tests/Feature/Api/
└── ArtistSearchApiTest.php          ✅
```

---

## Git History Reference

Key commits (most recent first):
- `743607a` - adding the artist search function
- `0152f38` - Merge PR #6: spotify-integration
- `5ccd918` - Address PR review comments
- `7d9e9fc` - creating the artist tables in prep for API integrations
- `a61918a` - Merge PR #5: use-psql (PostgreSQL migration)
- `b88d6e8` - Merge PR #4: ui-mockup
- `44cbe85` - Merge PR #3: Migrate mockup components
- `b9725c7` - Refactor: Migrate mockup components to main structure

---

## Notes for AI Assistants

When making changes to this codebase:

1. **Check this changelog first** to understand what exists and how components integrate
2. **Follow existing patterns** - especially for services, DTOs, and API resources
3. **Update this changelog** when adding significant features
4. **Reference CLAUDE.md** for business rules and architectural decisions
5. **Run tests** after changes: `./vendor/bin/pest`
6. **Run linting** after changes: `./vendor/bin/pint`
7. **Generate routes** if adding API endpoints: `php artisan wayfinder:generate`

### Integration Points
- Search page uses `ArtistSearchService` which combines local DB + Spotify
- Artists are auto-created from Spotify via background job
- Type-safe routes are generated from Laravel routes
- All API endpoints require authentication via `auth:web` middleware

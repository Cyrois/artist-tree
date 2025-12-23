# Artist-Tree Project Guidelines

## Project Overview
Artist-Tree is a festival lineup builder and artist discovery platform that helps music festival organizers create balanced lineups using data-driven insights from Spotify and YouTube APIs.

**Tech Stack:** Laravel 12 + Inertia.js v2 + Vue 3 + Laravel Cloud (MySQL)

## Documentation Structure

This project uses modular documentation:

- **CLAUDE.md** (this file): Business domain rules, architecture, and project-specific requirements
- **Agent Instructions** (`.claude/agents/`): Specialized AI agent roles and responsibilities
- **Framework Guidelines** (`.claude/guidelines/`): Implementation patterns and framework-specific rules

### Quick Navigation
- Laravel/PHP patterns: See `.claude/guidelines/backend-patterns.md`
- Vue/Inertia/Tailwind patterns: See `.claude/guidelines/frontend-patterns.md`
- Testing with Pest: See `.claude/guidelines/testing-patterns.md`
- Development workflow: See `.claude/guidelines/workflow.md`
- Complete Laravel Boost Guidelines: See `.claude/guidelines/laravel-boost.md`

For detailed navigation, see `.claude/README.md`.

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

### Route Structure
```
Routes:
├── web.php (Inertia pages)
│   ├── GET /dashboard → Inertia::render('Dashboard')
│   ├── GET /lineups → Inertia::render('Lineups/Index')
│   └── GET /lineups/{id} → Inertia::render('Lineups/Show')
│
└── api.php (RESTful JSON API)
    ├── GET /api/artists → JSON (paginated list)
    ├── GET /api/artists/search?q=... → JSON (autocomplete)
    ├── POST /api/lineups → JSON (create lineup)
    ├── POST /api/lineups/{id}/artists → JSON (add artist, recalculate tiers)
    ├── DELETE /api/lineups/{id}/artists/{artistId} → JSON
    └── POST /api/artists/{id}/refresh → JSON (refresh metrics)
```

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
**Business Rule:** Suggest tier placement based on artist scores, with manual override capability

**Technical Requirements:**
- Tiers: `headliner`, `sub_headliner`, `mid_tier`, `undercard`
- Algorithm **suggests** tier based on relative score within lineup
- Users can **manually override** tier assignment via drag-and-drop
- Store both suggested tier and actual tier in `lineup_artists` table
- Service class: `App\Services\TierCalculationService`

**Database Schema (lineup_artists):**
```sql
- tier (enum) -- current tier (may be manually set)
- suggested_tier (enum) -- what algorithm calculated
- tier_override (boolean, default false) -- user manually changed?
```

**Suggested Tier Calculation Logic:**
- Sort artists in lineup by score (descending)
- Top 10% = headliner (minimum 1)
- Next 20% = sub_headliner
- Next 40% = mid_tier
- Bottom 30% = undercard

**Manual Override Flow:**
1. Add artist → Algorithm calculates `suggested_tier`
2. Set `tier = suggested_tier`, `tier_override = false`
3. User drags artist to different tier → Set `tier_override = true`
4. Visual indicator shows manually placed artists
5. "Reset to Suggested" button → Copy `suggested_tier` to `tier`, set `tier_override = false`

**Recalculation Behavior:**
- When artist added/removed: Recalculate ALL `suggested_tier` values
- Artists with `tier_override = false` → Update `tier` to new `suggested_tier`
- Artists with `tier_override = true` → Keep current `tier`, update only `suggested_tier`
- This preserves manual placements while updating auto-assigned artists

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

#### YouTube API
**Service:** `App\Services\YouTubeService`

**Rules:**
- Daily quota: 10,000 units - monitor with `youtube_quota_usage` cache key
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

**Frontend Vue Component:**
```vue
<script setup>
import { ref, watch } from 'vue'
import { debounce } from 'lodash-es'

const searchQuery = ref('')
const results = ref([])

const searchArtists = debounce(async (query) => {
  if (!query || query.length < 2) return
  const response = await axios.get('/api/artists/search', { params: { q: query } })
  results.value = response.data.data
}, 300)

watch(searchQuery, (newValue) => searchArtists(newValue))
</script>
```

---

## Code Organization

```
app/
├── Services/              # Business logic, external APIs
│   ├── SpotifyService.php           # Spotify API integration
│   ├── YouTubeService.php           # YouTube API integration
│   ├── ArtistSearchService.php      # Artist discovery/creation
│   ├── ArtistScoringService.php     # Score calculation algorithm (org-aware)
│   ├── TierCalculationService.php   # Lineup tier assignments
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

**For detailed Laravel implementation patterns** (service classes, controller patterns, external APIs, policies, etc.), see `.claude/guidelines/backend-patterns.md`.

---

## Performance Requirements

**API Response Times:**
- Cached artist data: <500ms
- Artist search: <500ms
- Lineup tier recalculation: <1 second
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
- `TierCalculationService` - test tier assignments with different lineup sizes
- Spotify/YouTube API response parsing

### Feature Tests
**REQUIRED for:**
- All API endpoints (CRUD operations)
- Artist search functionality
- Lineup creation and artist addition
- Tier recalculation

### Mock External APIs
**NEVER hit real Spotify/YouTube APIs in tests** - always use `Http::fake()`.

**For detailed test examples, edge cases, factory patterns, and mocking strategies**, see `.claude/guidelines/testing-patterns.md`.

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

---

## Development Workflow

See `.claude/guidelines/workflow.md` for complete workflow, tooling, and quality checklist.

---

## Framework Implementation Guidelines

All framework-specific implementation guidelines have been moved to `.claude/guidelines/`:

- **Laravel Boost Guidelines**: `.claude/guidelines/laravel-boost.md`
- **Backend Patterns**: `.claude/guidelines/backend-patterns.md`
- **Frontend Patterns**: `.claude/guidelines/frontend-patterns.md`
- **Testing Patterns**: `.claude/guidelines/testing-patterns.md`
- **Development Workflow**: `.claude/guidelines/workflow.md`

AI agents and developers MUST consult these files for implementation.

---

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.1
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== wayfinder/core rules ===

## Laravel Wayfinder

Wayfinder generates TypeScript functions and types for Laravel controllers and routes which you can import into your client side code. It provides type safety and automatic synchronization between backend routes and frontend code.

### Development Guidelines
- Always use `search-docs` to check wayfinder correct usage before implementing any features.
- Always Prefer named imports for tree-shaking (e.g., `import { show } from '@/actions/...'`)
- Avoid default controller imports (prevents tree-shaking)
- Run `php artisan wayfinder:generate` after route changes if Vite plugin isn't installed

### Feature Overview
- Form Support: Use `.form()` with `--with-form` flag for HTML form attributes — `<form {...store.form()}>` → `action="/posts" method="post"`
- HTTP Methods: Call `.get()`, `.post()`, `.patch()`, `.put()`, `.delete()` for specific methods — `show.head(1)` → `{ url: "/posts/1", method: "head" }`
- Invokable Controllers: Import and invoke directly as functions. For example, `import StorePost from '@/actions/.../StorePostController'; StorePost()`
- Named Routes: Import from `@/routes/` for non-controller routes. For example, `import { show } from '@/routes/post'; show(1)` for route name `post.show`
- Parameter Binding: Detects route keys (e.g., `{post:slug}`) and accepts matching object properties — `show("my-post")` or `show({ slug: "my-post" })`
- Query Merging: Use `mergeQuery` to merge with `window.location.search`, set values to `null` to remove — `show(1, { mergeQuery: { page: 2, sort: null } })`
- Query Parameters: Pass `{ query: {...} }` in options to append params — `show(1, { query: { page: 1 } })` → `"/posts/1?page=1"`
- Route Objects: Functions return `{ url, method }` shaped objects — `show(1)` → `{ url: "/posts/1", method: "get" }`
- URL Extraction: Use `.url()` to get URL string — `show.url(1)` → `"/posts/1"`

### Example Usage

<code-snippet name="Wayfinder Basic Usage" lang="typescript">
    // Import controller methods (tree-shakable)
    import { show, store, update } from '@/actions/App/Http/Controllers/PostController'

    // Get route object with URL and method...
    show(1) // { url: "/posts/1", method: "get" }

    // Get just the URL...
    show.url(1) // "/posts/1"

    // Use specific HTTP methods...
    show.get(1) // { url: "/posts/1", method: "get" }
    show.head(1) // { url: "/posts/1", method: "head" }

    // Import named routes...
    import { show as postShow } from '@/routes/post' // For route name 'post.show'
    postShow(1) // { url: "/posts/1", method: "get" }
</code-snippet>


### Wayfinder + Inertia
If your application uses the `<Form>` component from Inertia, you can use Wayfinder to generate form action and method automatically.
<code-snippet name="Wayfinder Form Component (Vue)" lang="vue">

<Form v-bind="store.form()"><input name="title" /></Form>

</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="vue">

    import { Link } from '@inertiajs/vue3'
    <Link href="/">Home</Link>

</code-snippet>


=== inertia-vue/v2/forms rules ===

## Inertia + Vue Forms

<code-snippet name="`<Form>` Component Example" lang="vue">

<Form
    action="/users"
    method="post"
    #default="{
        errors,
        hasErrors,
        processing,
        progress,
        wasSuccessful,
        recentlySuccessful,
        setError,
        clearErrors,
        resetAndClearErrors,
        defaults,
        isDirty,
        reset,
        submit,
  }"
>
    <input type="text" name="name" />

    <div v-if="errors.name">
        {{ errors.name }}
    </div>

    <button type="submit" :disabled="processing">
        {{ processing ? 'Creating...' : 'Create User' }}
    </button>

    <div v-if="wasSuccessful">User created successfully!</div>
</Form>

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>

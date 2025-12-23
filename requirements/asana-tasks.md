# Artist-Tree Project Tasks
**Tech Stack:** Laravel 12 + Inertia.js v2 + Vue 3 + Laravel Cloud
**Architecture:** Hybrid (Inertia.js pages + RESTful API)
**Last updated:** 2025-12-21
**AI Implementation:** Claude agents will implement majority of tasks with human oversight

---

## Summary
- **Total Tasks:** 68
- **Status:** All incomplete
- **Collaboration Model:** AI-driven development with human review and approval

---

## Phase 0: Pre-Development

### Task 1: Technical Architecture Decision
**Priority:** High
**Tags:** setup, architecture
**Assigned To:** Human + AI Collaboration

**Business Context:**
Establish the foundational technology stack to ensure scalability, maintainability, and developer productivity throughout the project lifecycle.

**Technical Implementation:**
- **Backend:** Laravel 12.x for monolithic application (Inertia pages + RESTful API endpoints)
- **Frontend:** Inertia.js v2 + Vue 3 with Composition API for reactive UI
- **Database:** PostgreSQL for managed database with automatic backups and scaling
- **Hosting:** TBD (single monolithic deployment)
- **Architecture:** Hybrid approach - Inertia for page navigation, API for interactivity

**AI Agent Tasks:**
- Research and document Laravel + Inertia.js best practices
- Document RESTful API design patterns within Laravel monolith
- Document Laravel Cloud database setup and migration patterns
- Create architecture decision record (ADR) for hybrid approach
- Document when to use Inertia vs API endpoints

**Human Oversight:**
- Approve technology choices
- Review security implications
- Validate hosting strategy
- Sign off on ADR

**Acceptance Criteria:**
- [ ] Tech stack documented in ADR
- [ ] Development environment requirements specified
- [ ] CI/CD pipeline approach defined
- [ ] Security and compliance requirements identified

---

### Task 2: Project Setup
**Priority:** High
**Tags:** setup, infrastructure
**Dependencies:** Technical Architecture Decision

**Business Context:**
Initialize development environment and project scaffolding to enable immediate development productivity.

**Technical Implementation:**
- Create Laravel 12.x project with Inertia.js starter kit
- Configure Vue 3 with Inertia.js v2
- Set up Laravel Cloud project and database connection
- Configure local development environment with Vite

**AI Agent Tasks:**
```bash
# Laravel setup (already completed - current project)
# Project already initialized with Inertia.js + Vue 3

# Verify Inertia installation
php artisan about

# Install additional dependencies
composer require laravel/sanctum
npm install axios lodash-es

# Environment configuration
cp .env.example .env
# Configure Laravel Cloud database connection in .env
php artisan key:generate
```

**Laravel Configuration:**
- Configure `config/database.php` for PostgreSQL
- Verify Inertia.js middleware in `bootstrap/app.php`
- Configure Laravel Sanctum for cookie-based authentication
- Set up API routes in `routes/api.php` for interactive features
- Configure `.env` with Laravel Cloud database credentials and API keys

**Frontend Configuration (Already Configured):**
- Inertia.js v2 already installed and configured
- Vue 3 with Composition API and `<script setup>` syntax
- Tailwind CSS v4 already configured
- Vite build tool configured with Laravel Wayfinder plugin
- Axios for API calls within Vue components

**Human Oversight:**
- Provide API keys (Spotify, YouTube) and database credentials
- Review security configurations (Sanctum, rate limiting)
- Approve dependency versions
- Test local environment setup

**Acceptance Criteria:**
- [ ] Laravel app serves Inertia pages on `http://localhost:8000`
- [ ] Vite dev server running with HMR (Hot Module Replacement)
- [ ] Laravel Cloud database connection verified
- [ ] Git repository initialized with `.gitignore`
- [ ] README with setup instructions updated
- [ ] Example Inertia page renders correctly

---

### Task 3: Data Model Design
**Priority:** High
**Tags:** database, architecture
**Dependencies:** Project Setup

**Business Context:**
Design database schema to support artist data, metrics tracking, lineup management, and tier calculations with optimal query performance.

**Technical Implementation (Laravel Migrations):**

**AI Agent Tasks:**
Create Laravel migration files using `php artisan make:migration`:

```php
// database/migrations/xxxx_create_artists_table.php
Schema::create('artists', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('spotify_id')->unique()->nullable();
    $table->string('youtube_channel_id')->nullable();
    $table->json('genre')->nullable();
    $table->string('image_url')->nullable();
    $table->timestamps();

    $table->fullText('name');
    $table->index('spotify_id');
});

// database/migrations/xxxx_create_artist_metrics_table.php
Schema::create('artist_metrics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('artist_id')->constrained()->onDelete('cascade');
    $table->decimal('score', 5, 2);
    $table->unsignedBigInteger('spotify_monthly_listeners')->nullable();
    $table->unsignedBigInteger('youtube_subscribers')->nullable();
    $table->decimal('engagement_rate', 5, 2)->nullable();
    $table->timestamp('last_refreshed')->nullable();
    $table->timestamps();

    $table->index('artist_id');
    $table->index(['score' => 'desc']);
});

// database/migrations/xxxx_create_lineups_table.php
Schema::create('lineups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();

    $table->index('user_id');
});

// database/migrations/xxxx_create_lineup_artists_table.php
Schema::create('lineup_artists', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lineup_id')->constrained()->onDelete('cascade');
    $table->foreignId('artist_id')->constrained()->onDelete('cascade');
    $table->enum('tier', ['headliner', 'sub_headliner', 'mid_tier', 'undercard']);
    $table->integer('position')->nullable();
    $table->timestamp('added_at')->useCurrent();

    $table->unique(['lineup_id', 'artist_id']);
    $table->index('lineup_id');
    $table->index('tier');
});
```

**Laravel Eloquent Models:**
```php
// app/Models/Artist.php
class Artist extends Model {
    protected $fillable = ['name', 'spotify_id', 'youtube_channel_id', 'genre', 'image_url'];
    protected $casts = ['genre' => 'array'];

    public function metrics() {
        return $this->hasMany(ArtistMetric::class);
    }

    public function lineups() {
        return $this->belongsToMany(Lineup::class, 'lineup_artists')
                    ->withPivot('tier', 'position', 'added_at');
    }
}
```

**Human Oversight:**
- Review data normalization approach
- Validate index strategy for query performance
- Approve foreign key constraints
- Ensure data types match business requirements

**Acceptance Criteria:**
- [ ] All Laravel migrations created and run successfully
- [ ] Eloquent models with relationships defined
- [ ] Database migrations versioned in Git
- [ ] Laravel authorization policies defined where needed
- [ ] Seed data script for testing

---

## Day 1: APIs and Data Layer

### Task 4: Spotify API Setup
**Priority:** High
**Tags:** integration, external-api
**Dependencies:** Data Model Design

**Business Context:**
Integrate with Spotify Web API to fetch artist popularity metrics (monthly listeners, follower count, genre data) for comprehensive artist profiling.

**Technical Implementation (Laravel Service):**

**AI Agent Tasks:**
```php
// app/Services/SpotifyService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SpotifyService {
    private string $clientId;
    private string $clientSecret;
    private ?string $accessToken = null;

    public function __construct() {
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
    }

    private function authenticate(): string {
        return Cache::remember('spotify_access_token', 3600, function() {
            $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            return $response->json()['access_token'];
        });
    }

    public function searchArtist(string $name): ?array {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $name,
                'type' => 'artist',
                'limit' => 1
            ]);

        $artists = $response->json()['artists']['items'] ?? [];
        return $artists[0] ?? null;
    }
}
```

**Configuration:**
```php
// config/services.php
'spotify' => [
    'client_id' => env('SPOTIFY_CLIENT_ID'),
    'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
],
```

**Human Oversight:**
- Provide Spotify Developer App credentials
- Review API rate limit handling
- Approve caching strategy
- Validate error responses

**Acceptance Criteria:**
- [ ] Spotify OAuth authentication working
- [ ] Access token cached for reuse
- [ ] Search API returns artist data
- [ ] Error handling for rate limits
- [ ] Unit tests for authentication flow

---

### Task 5: YouTube API Setup
**Priority:** High
**Tags:** integration, external-api
**Dependencies:** Data Model Design

**Business Context:**
Integrate with YouTube Data API v3 to fetch channel subscriber counts and video view metrics as additional artist popularity indicators.

**Technical Implementation (Laravel Service):**

**AI Agent Tasks:**
```php
// app/Services/YouTubeService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class YouTubeService {
    private string $apiKey;

    public function __construct() {
        $this->apiKey = config('services.youtube.api_key');
    }

    public function searchChannel(string $channelName): ?array {
        $cacheKey = "youtube_search_" . md5($channelName);

        return Cache::remember($cacheKey, 3600, function() use ($channelName) {
            $response = Http::get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => $channelName,
                'type' => 'channel',
                'maxResults' => 1,
                'key' => $this->apiKey
            ]);

            $items = $response->json()['items'] ?? [];
            return $items[0] ?? null;
        });
    }

    public function getChannelStats(string $channelId): ?array {
        $response = Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'statistics',
            'id' => $channelId,
            'key' => $this->apiKey
        ]);

        $items = $response->json()['items'] ?? [];
        return $items[0]['statistics'] ?? null;
    }
}
```

**Human Oversight:**
- Provide YouTube API key from Google Cloud Console
- Review quota usage (10,000 units/day limit)
- Approve channel matching logic
- Test with ambiguous artist names

**Acceptance Criteria:**
- [ ] YouTube API key configured
- [ ] Channel search returns correct results
- [ ] Subscriber count fetched successfully
- [ ] Quota monitoring implemented
- [ ] Fallback for missing channels

---

### Task 6: Spotify Artist Search Service
**Priority:** High
**Tags:** backend, service-layer
**Dependencies:** Spotify API Setup

**Business Context:**
Build a Laravel service layer that searches for artists on Spotify and maps the response data to our application's data model.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/ArtistSearchService.php
namespace App\Services;

use App\Models\Artist;
use App\Models\ArtistMetric;

class ArtistSearchService {
    public function __construct(
        private SpotifyService $spotify,
        private YouTubeService $youtube
    ) {}

    public function findOrCreateFromSpotify(string $artistName): Artist {
        // Search Spotify
        $spotifyData = $this->spotify->searchArtist($artistName);

        if (!$spotifyData) {
            throw new \Exception("Artist not found on Spotify");
        }

        // Check if artist exists
        $artist = Artist::where('spotify_id', $spotifyData['id'])->first();

        if ($artist) {
            return $artist;
        }

        // Create new artist
        $artist = Artist::create([
            'name' => $spotifyData['name'],
            'spotify_id' => $spotifyData['id'],
            'genre' => $spotifyData['genres'] ?? [],
            'image_url' => $spotifyData['images'][0]['url'] ?? null,
        ]);

        // Create metrics record
        ArtistMetric::create([
            'artist_id' => $artist->id,
            'spotify_monthly_listeners' => $spotifyData['followers']['total'] ?? 0,
            'score' => 0, // Will be calculated later
        ]);

        return $artist;
    }
}
```

**Human Oversight:**
- Review duplicate detection logic
- Validate genre classification
- Approve image selection (Spotify returns multiple sizes)
- Test edge cases (artists with special characters)

**Acceptance Criteria:**
- [ ] Creates artist record if not exists
- [ ] Updates existing artist if found
- [ ] Stores Spotify metadata correctly
- [ ] Handles missing optional fields
- [ ] Logs search failures

---

### Task 7: Spotify Artist Data Fetcher
**Priority:** High
**Tags:** backend, data-enrichment
**Dependencies:** Spotify Artist Search Service

**Business Context:**
Fetch comprehensive artist data including follower count, popularity score, and genre information to enrich artist profiles.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/ArtistEnrichmentService.php
public function enrichFromSpotify(Artist $artist): void {
    if (!$artist->spotify_id) {
        return;
    }

    $token = $this->spotify->authenticate();
    $response = Http::withToken($token)
        ->get("https://api.spotify.com/v1/artists/{$artist->spotify_id}");

    $data = $response->json();

    $artist->update([
        'genre' => $data['genres'] ?? [],
        'image_url' => $data['images'][0]['url'] ?? $artist->image_url,
    ]);

    $artist->metrics()->create([
        'spotify_monthly_listeners' => $data['followers']['total'] ?? 0,
        'score' => $this->calculateScore($data),
        'last_refreshed' => now(),
    ]);
}

private function calculateScore(array $spotifyData): float {
    $followers = $spotifyData['followers']['total'] ?? 0;
    $popularity = $spotifyData['popularity'] ?? 0;

    // Weighted scoring algorithm
    $followerScore = min(($followers / 1000000) * 50, 50); // Max 50 points
    $popularityScore = ($popularity / 100) * 50; // Max 50 points

    return round($followerScore + $popularityScore, 2);
}
```

**Human Oversight:**
- Review scoring algorithm weights
- Validate score distribution across artist tiers
- Approve data refresh frequency
- Test with outlier artists (mega-stars vs unsigned)

**Acceptance Criteria:**
- [ ] Fetches complete artist profile
- [ ] Updates existing metrics
- [ ] Score calculation tested with sample data
- [ ] Timestamps track last refresh
- [ ] Graceful handling of API errors

---

### Task 8: YouTube Channel Search Service
**Priority:** High
**Tags:** backend, service-layer
**Dependencies:** YouTube API Setup

**Business Context:**
Match artists to their YouTube channels to gather video view counts and subscriber metrics as supplementary popularity indicators.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/YouTubeChannelMatcher.php
namespace App\Services;

class YouTubeChannelMatcher {
    public function __construct(private YouTubeService $youtube) {}

    public function findChannelForArtist(string $artistName): ?string {
        $searchResult = $this->youtube->searchChannel($artistName);

        if (!$searchResult) {
            return null;
        }

        // Extract channel ID from search result
        $channelId = $searchResult['id']['channelId'] ?? null;

        // Validate channel title matches artist name (fuzzy match)
        $channelTitle = $searchResult['snippet']['title'] ?? '';
        $similarity = similar_text(
            strtolower($artistName),
            strtolower($channelTitle)
        );

        // Require at least 70% similarity
        if ($similarity / strlen($artistName) < 0.7) {
            \Log::warning("YouTube channel mismatch", [
                'artist' => $artistName,
                'channel' => $channelTitle,
                'similarity' => $similarity
            ]);
            return null;
        }

        return $channelId;
    }
}
```

**Human Oversight:**
- Review channel matching algorithm
- Adjust similarity threshold based on testing
- Handle artists with multiple channels (VEVO, topic, official)
- Approve manual override mechanism

**Acceptance Criteria:**
- [ ] Matches channels with >70% name similarity
- [ ] Logs mismatches for review
- [ ] Returns null for no match
- [ ] Caches channel IDs
- [ ] Unit tests for edge cases

---

### Task 9: YouTube Channel Stats Fetcher
**Priority:** High
**Tags:** backend, data-enrichment
**Dependencies:** YouTube Channel Search Service

**Business Context:**
Fetch YouTube subscriber counts and view statistics to complement Spotify data for more comprehensive artist scoring.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// Add to app/Services/ArtistEnrichmentService.php
public function enrichFromYouTube(Artist $artist): void {
    if (!$artist->youtube_channel_id) {
        // Try to find channel
        $channelId = $this->youtubeMatcher->findChannelForArtist($artist->name);
        if ($channelId) {
            $artist->update(['youtube_channel_id' => $channelId]);
        } else {
            return; // No YouTube channel found
        }
    }

    $stats = $this->youtube->getChannelStats($artist->youtube_channel_id);

    if (!$stats) {
        return;
    }

    $metric = $artist->metrics()->latest()->first();
    $metric->update([
        'youtube_subscribers' => $stats['subscriberCount'] ?? 0,
        'engagement_rate' => $this->calculateEngagementRate($stats),
        'last_refreshed' => now(),
    ]);

    // Recalculate score with YouTube data
    $metric->score = $this->calculateCombinedScore($metric);
    $metric->save();
}

private function calculateEngagementRate(array $stats): float {
    $subscribers = $stats['subscriberCount'] ?? 1;
    $views = $stats['viewCount'] ?? 0;

    return round(($views / $subscribers) * 100, 2);
}
```

**Human Oversight:**
- Review engagement rate formula
- Validate combined scoring with both platforms
- Test with YouTube-only artists
- Approve data storage strategy

**Acceptance Criteria:**
- [ ] Fetches subscriber count
- [ ] Calculates engagement rate
- [ ] Updates combined score
- [ ] Handles channels without public subscriber counts
- [ ] Integration test with real data

---

### Task 10: API Error Handling & Retry Logic
**Priority:** High
**Tags:** backend, reliability
**Dependencies:** YouTube Channel Stats Fetcher

**Business Context:**
Implement robust error handling and retry mechanisms to ensure data fetching reliability despite external API failures or rate limits.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/ExternalApiClient.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class ExternalApiClient {
    private int $maxRetries = 3;
    private int $retryDelay = 1000; // milliseconds

    public function get(string $url, array $params = [], array $headers = []): array {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(10)
                    ->retry(3, 1000, function ($exception) {
                        return $exception instanceof RequestException &&
                               $exception->response->status() >= 500;
                    })
                    ->get($url, $params);

                if ($response->successful()) {
                    return $response->json();
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After') ?? 60;
                    \Log::warning("Rate limited, waiting {$retryAfter}s", ['url' => $url]);
                    sleep($retryAfter);
                    $attempt++;
                    continue;
                }

                throw new \Exception("API request failed: {$response->status()}");

            } catch (\Exception $e) {
                $attempt++;
                \Log::error("API request failed", [
                    'url' => $url,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                usleep($this->retryDelay * 1000);
            }
        }

        throw new \Exception("Max retries exceeded for $url");
    }
}
```

**Human Oversight:**
- Review retry strategy (exponential backoff vs fixed delay)
- Approve timeout values
- Validate error logging
- Test circuit breaker pattern for cascading failures

**Acceptance Criteria:**
- [ ] Retries failed requests up to 3 times
- [ ] Respects rate limit headers
- [ ] Logs all API failures
- [ ] Throws exception after max retries
- [ ] Unit tests for retry logic

---

### Task 11: API Response Caching Layer
**Priority:** High
**Tags:** backend, performance
**Dependencies:** API Error Handling and Retry Logic

**Business Context:**
Implement caching to reduce external API calls, improve response times, and stay within rate limits while providing fresh data.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// Update SpotifyService and YouTubeService with caching
public function searchArtist(string $name): ?array {
    $cacheKey = "spotify_artist_" . md5(strtolower($name));
    $cacheDuration = 86400; // 24 hours

    return Cache::remember($cacheKey, $cacheDuration, function() use ($name) {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->get('https://api.spotify.com/v1/search', [
                'q' => $name,
                'type' => 'artist',
                'limit' => 1
            ]);

        $artists = $response->json()['artists']['items'] ?? [];
        return $artists[0] ?? null;
    });
}

// Create cache management command
// app/Console/Commands/ClearArtistCache.php
class ClearArtistCache extends Command {
    protected $signature = 'cache:clear-artists {artist_id?}';

    public function handle() {
        if ($artistId = $this->argument('artist_id')) {
            $artist = Artist::findOrFail($artistId);
            Cache::forget("spotify_artist_" . md5(strtolower($artist->name)));
            $this->info("Cleared cache for {$artist->name}");
        } else {
            Cache::flush();
            $this->info("Cleared all artist caches");
        }
    }
}
```

**Caching Strategy:**
- Spotify artist search: 24 hours
- YouTube channel search: 7 days (channels rarely change)
- Artist metrics: 6 hours (updates twice daily)
- Authentication tokens: 55 minutes (Spotify tokens expire in 60 min)

**Human Oversight:**
- Approve cache durations
- Review cache invalidation strategy
- Test cache warming on deployment
- Monitor cache hit rates

**Acceptance Criteria:**
- [ ] Cache configured in `config/cache.php`
- [ ] Redis or file-based cache working
- [ ] Cache keys namespaced properly
- [ ] Manual cache clear command works
- [ ] Cache metrics logged

---

### Task 12: Artist Enrichment Service
**Priority:** High
**Tags:** backend, orchestration
**Dependencies:** Spotify + YouTube Fetchers, Caching Layer

**Business Context:**
Create a unified service that orchestrates data fetching from both Spotify and YouTube, manages data updates, and triggers score recalculation.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/ArtistEnrichmentService.php (Complete version)
namespace App\Services;

use App\Models\Artist;
use App\Jobs\EnrichArtistJob;

class ArtistEnrichmentService {
    public function __construct(
        private SpotifyService $spotify,
        private YouTubeService $youtube,
        private YouTubeChannelMatcher $youtubeMatcher
    ) {}

    public function enrich(Artist $artist, bool $async = true): void {
        if ($async) {
            EnrichArtistJob::dispatch($artist);
            return;
        }

        $this->enrichNow($artist);
    }

    private function enrichNow(Artist $artist): void {
        try {
            $this->enrichFromSpotify($artist);
            $this->enrichFromYouTube($artist);

            \Log::info("Artist enriched successfully", [
                'artist_id' => $artist->id,
                'name' => $artist->name
            ]);
        } catch (\Exception $e) {
            \Log::error("Artist enrichment failed", [
                'artist_id' => $artist->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // enrichFromSpotify and enrichFromYouTube methods from previous tasks...
}

// app/Jobs/EnrichArtistJob.php
namespace App\Jobs;

use App\Models\Artist;
use App\Services\ArtistEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnrichArtistJob implements ShouldQueue {
    use Queueable;

    public function __construct(public Artist $artist) {}

    public function handle(ArtistEnrichmentService $enrichment): void {
        $enrichment->enrich($this->artist, false);
    }
}
```

**Human Oversight:**
- Review queue configuration for background jobs
- Approve async vs sync enrichment strategy
- Test failure scenarios
- Monitor job success rates

**Acceptance Criteria:**
- [ ] Enriches from both Spotify and YouTube
- [ ] Queues jobs for async processing
- [ ] Updates artist and metrics records
- [ ] Handles partial failures gracefully
- [ ] Job monitoring dashboard configured

---

### Task 13: Design Scoring Algorithm
**Priority:** High
**Tags:** algorithm, business-logic
**Dependencies:** API Response Caching Layer

**Business Context:**
Define a scoring algorithm that objectively ranks artists based on quantifiable metrics (Spotify listeners, YouTube subscribers, engagement) to enable automatic tier classification.

**Technical Implementation:**

**AI Agent Tasks:**
Document algorithm in `docs/scoring-algorithm.md`:

```markdown
# Artist Scoring Algorithm

## Formula
```
score = (spotifyScore * 0.6) + (youtubeScore * 0.3) + (engagementScore * 0.1)
```

## Component Calculations

### Spotify Score (0-60 points)
- Based on monthly listeners
- Logarithmic scale to handle outliers

```
spotifyScore = min(60, (log10(monthlyListeners + 1) / log10(100000000)) * 60)
```

### YouTube Score (0-30 points)
- Based on subscriber count

```
youtubeScore = min(30, (log10(subscribers + 1) / log10(50000000)) * 30)
```

### Engagement Score (0-10 points)
- Based on YouTube views-to-subscribers ratio

```
engagementRate = totalViews / subscribers
engagementScore = min(10, (engagementRate / 1000) * 10)
```

## Tier Classification
- **Headliner:** 80-100 (top 10% of lineup)
- **Sub-Headliner:** 60-79 (next 20%)
- **Mid-Tier:** 40-59 (next 40%)
- **Undercard:** 0-39 (bottom 30%)
```

**Human Oversight:**
- Review and approve algorithm weights
- Validate against real-world festival lineups
- Adjust thresholds based on genre (EDM vs indie)
- Sign off on tier boundaries

**Acceptance Criteria:**
- [ ] Algorithm documented with examples
- [ ] Weights justified with data
- [ ] Tier thresholds defined
- [ ] Edge cases identified (new artists, disbanded groups)
- [ ] Human review and approval completed

---

### Task 14: Implement Scoring Algorithm
**Priority:** High
**Tags:** backend, algorithm
**Dependencies:** Design Scoring Algorithm

**Business Context:**
Implement the approved scoring algorithm in Laravel to automatically calculate artist scores based on metrics from Spotify and YouTube.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/ScoringService.php
namespace App\Services;

use App\Models\ArtistMetric;

class ScoringService {
    // Weights
    private const SPOTIFY_WEIGHT = 0.6;
    private const YOUTUBE_WEIGHT = 0.3;
    private const ENGAGEMENT_WEIGHT = 0.1;

    // Normalization constants
    private const MAX_SPOTIFY_LISTENERS = 100000000; // 100M
    private const MAX_YOUTUBE_SUBSCRIBERS = 50000000; // 50M
    private const MAX_ENGAGEMENT_RATE = 1000;

    public function calculateScore(ArtistMetric $metric): float {
        $spotifyScore = $this->calculateSpotifyScore($metric->spotify_monthly_listeners ?? 0);
        $youtubeScore = $this->calculateYouTubeScore($metric->youtube_subscribers ?? 0);
        $engagementScore = $this->calculateEngagementScore($metric->engagement_rate ?? 0);

        $totalScore = ($spotifyScore * self::SPOTIFY_WEIGHT) +
                     ($youtubeScore * self::YOUTUBE_WEIGHT) +
                     ($engagementScore * self::ENGAGEMENT_WEIGHT);

        return round($totalScore, 2);
    }

    private function calculateSpotifyScore(int $listeners): float {
        if ($listeners <= 0) return 0;

        $normalized = log10($listeners + 1) / log10(self::MAX_SPOTIFY_LISTENERS);
        return min(60, $normalized * 60);
    }

    private function calculateYouTubeScore(int $subscribers): float {
        if ($subscribers <= 0) return 0;

        $normalized = log10($subscribers + 1) / log10(self::MAX_YOUTUBE_SUBSCRIBERS);
        return min(30, $normalized * 30);
    }

    private function calculateEngagementScore(float $engagementRate): float {
        $normalized = $engagementRate / self::MAX_ENGAGEMENT_RATE;
        return min(10, $normalized * 10);
    }

    public function determineArtistTier(float $score): string {
        return match(true) {
            $score >= 80 => 'headliner',
            $score >= 60 => 'sub_headliner',
            $score >= 40 => 'mid_tier',
            default => 'undercard',
        };
    }
}
```

**Human Oversight:**
- Review implementation against approved algorithm
- Validate with sample data
- Test edge cases (zero values, missing data)
- Approve before integration

**Acceptance Criteria:**
- [ ] Implements approved algorithm exactly
- [ ] Returns scores 0-100
- [ ] Handles missing data gracefully
- [ ] Tier determination matches spec
- [ ] Code documented with examples

---

### Task 15: Write Scoring Tests
**Priority:** Medium
**Tags:** testing, quality-assurance
**Dependencies:** Implement Scoring Algorithm

**Business Context:**
Ensure scoring algorithm produces consistent, predictable results across various artist profiles and edge cases.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// tests/Unit/ScoringServiceTest.php
namespace Tests\Unit;

use App\Services\ScoringService;
use App\Models\ArtistMetric;
use Tests\TestCase;

class ScoringServiceTest extends TestCase {
    private ScoringService $scoringService;

    protected function setUp(): void {
        parent::setUp();
        $this->scoringService = new ScoringService();
    }

    public function test_mega_star_artist_scores_above_80(): void {
        $metric = new ArtistMetric([
            'spotify_monthly_listeners' => 50000000, // 50M
            'youtube_subscribers' => 20000000, // 20M
            'engagement_rate' => 500
        ]);

        $score = $this->scoringService->calculateScore($metric);

        $this->assertGreaterThan(80, $score);
        $this->assertEquals('headliner', $this->scoringService->determineArtistTier($score));
    }

    public function test_mid_tier_artist_scores_40_to_60(): void {
        $metric = new ArtistMetric([
            'spotify_monthly_listeners' => 500000, // 500K
            'youtube_subscribers' => 100000, // 100K
            'engagement_rate' => 50
        ]);

        $score = $this->scoringService->calculateScore($metric);

        $this->assertGreaterThanOrEqual(40, $score);
        $this->assertLessThan(60, $score);
    }

    public function test_handles_zero_values(): void {
        $metric = new ArtistMetric([
            'spotify_monthly_listeners' => 0,
            'youtube_subscribers' => 0,
            'engagement_rate' => 0
        ]);

        $score = $this->scoringService->calculateScore($metric);

        $this->assertEquals(0, $score);
    }

    public function test_spotify_only_artist(): void {
        $metric = new ArtistMetric([
            'spotify_monthly_listeners' => 1000000,
            'youtube_subscribers' => null,
            'engagement_rate' => null
        ]);

        $score = $this->scoringService->calculateScore($metric);

        $this->assertGreaterThan(0, $score);
    }
}
```

**Human Oversight:**
- Review test coverage
- Add test cases for real artists
- Validate tier boundaries
- Approve test suite

**Acceptance Criteria:**
- [ ] 100% code coverage of scoring service
- [ ] Tests for all tiers
- [ ] Edge case tests (nulls, zeros, outliers)
- [ ] Regression tests for algorithm changes
- [ ] Tests pass in CI pipeline

---

### Task 16: API - List Artists Endpoint
**Priority:** High
**Tags:** backend, api
**Dependencies:** Seed Database with Artists

**Business Context:**
Provide a paginated endpoint to list all artists with their latest metrics for browse functionality.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// routes/api.php
Route::prefix('artists')->group(function () {
    Route::get('/', [ArtistController::class, 'index']);
    Route::get('/{id}', [ArtistController::class, 'show']);
    Route::get('/search', [ArtistController::class, 'search']);
    Route::post('/{id}/refresh', [ArtistController::class, 'refresh']);
});

// app/Http/Controllers/ArtistController.php
namespace App\Http\Controllers;

use App\Models\Artist;
use App\Http\Resources\ArtistResource;
use Illuminate\Http\Request;

class ArtistController extends Controller {
    public function index(Request $request) {
        $artists = Artist::with(['metrics' => function($query) {
            $query->latest();
        }])
        ->orderBy('name')
        ->paginate(20);

        return ArtistResource::collection($artists);
    }
}

// app/Http/Resources/ArtistResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource {
    public function toArray($request) {
        $latestMetric = $this->metrics->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'genre' => $this->genre,
            'image_url' => $this->image_url,
            'spotify_id' => $this->spotify_id,
            'metrics' => [
                'score' => $latestMetric?->score ?? 0,
                'spotify_monthly_listeners' => $latestMetric?->spotify_monthly_listeners,
                'youtube_subscribers' => $latestMetric?->youtube_subscribers,
                'tier' => $this->determineTier($latestMetric?->score ?? 0),
                'last_refreshed' => $latestMetric?->last_refreshed,
            ],
        ];
    }

    private function determineTier(float $score): string {
        return match(true) {
            $score >= 80 => 'headliner',
            $score >= 60 => 'sub_headliner',
            $score >= 40 => 'mid_tier',
            default => 'undercard',
        };
    }
}
```

**Human Oversight:**
- Review API response structure
- Test pagination performance with large datasets
- Approve rate limiting strategy
- Validate JSON structure

**Acceptance Criteria:**
- [ ] Returns paginated list of artists
- [ ] Includes latest metrics for each artist
- [ ] Response time < 500ms for 10,000 artists
- [ ] Proper HTTP status codes (200, 500)
- [ ] API documentation generated

---

### Task 17: API - Get Single Artist Endpoint
**Priority:** High
**Tags:** backend, api
**Dependencies:** API - List Artists Endpoint

**Business Context:**
Fetch detailed information about a single artist including complete metrics history.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Http/Controllers/ArtistController.php
public function show(string $id) {
    $artist = Artist::with('metrics')
        ->findOrFail($id);

    return new ArtistResource($artist);
}

// Enhanced ArtistResource for single artist
class ArtistResource extends JsonResource {
    public function toArray($request) {
        $isSingleResource = !$this->resource instanceof \Illuminate\Pagination\LengthAwarePaginator;

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'genre' => $this->genre,
            'image_url' => $this->image_url,
            'spotify_id' => $this->spotify_id,
            'youtube_channel_id' => $this->youtube_channel_id,
            'created_at' => $this->created_at,
        ];

        // Include full metrics history for single resource
        if ($isSingleResource) {
            $data['metrics_history'] = $this->metrics->map(fn($metric) => [
                'score' => $metric->score,
                'spotify_monthly_listeners' => $metric->spotify_monthly_listeners,
                'youtube_subscribers' => $metric->youtube_subscribers,
                'engagement_rate' => $metric->engagement_rate,
                'recorded_at' => $metric->created_at,
            ]);
        }

        $latestMetric = $this->metrics->first();
        $data['current_metrics'] = [
            'score' => $latestMetric?->score ?? 0,
            'spotify_monthly_listeners' => $latestMetric?->spotify_monthly_listeners,
            'youtube_subscribers' => $latestMetric?->youtube_subscribers,
            'tier' => $this->determineTier($latestMetric?->score ?? 0),
            'last_refreshed' => $latestMetric?->last_refreshed,
        ];

        return $data;
    }
}
```

**Human Oversight:**
- Review data exposure (ensure no sensitive data)
- Test with various artist IDs
- Validate 404 handling
- Approve metrics history format

**Acceptance Criteria:**
- [ ] Returns complete artist details
- [ ] Includes metrics history
- [ ] 404 for non-existent artists
- [ ] Response time < 200ms
- [ ] Proper error messages

---

### Task 18: API - Search Artists Endpoint
**Priority:** High
**Tags:** backend, api, search
**Dependencies:** Artist Enrichment Service

**Business Context:**
Enable users to search for artists by name with autocomplete support for lineup building.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Http/Controllers/ArtistController.php
public function search(Request $request) {
    $request->validate([
        'q' => 'required|string|min:2|max:100',
        'limit' => 'nullable|integer|min:1|max:50',
    ]);

    $query = $request->input('q');
    $limit = $request->input('limit', 20);

    $artists = Artist::whereRaw(
        "to_tsvector('english', name) @@ plainto_tsquery('english', ?)",
        [$query]
    )
    ->orWhere('name', 'ILIKE', "%{$query}%")
    ->with(['metrics' => function($q) {
        $q->latest();
    }])
    ->limit($limit)
    ->get();

    return ArtistResource::collection($artists);
}
```

**Laravel Scout Full-Text Search (Already configured in Task 3 migrations):**
```php
// Full-text index created in migration:
$table->fullText('name');

// Or use Laravel Scout for advanced search:
// composer require laravel/scout
// Add Searchable trait to Artist model
```

**Human Oversight:**
- Test search relevance with various queries
- Validate performance with large dataset
- Approve ranking algorithm
- Test special characters and Unicode

**Acceptance Criteria:**
- [ ] Returns relevant results for partial matches
- [ ] Full-text search working
- [ ] Response time < 300ms
- [ ] Handles typos gracefully
- [ ] Autocomplete-friendly format

---

### Task 19: API - Refresh Artist Data Endpoint
**Priority:** Medium
**Tags:** backend, api, data-refresh
**Dependencies:** Artist Enrichment Service

**Business Context:**
Allow manual refresh of artist data when users notice outdated metrics or new artists.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Http/Controllers/ArtistController.php
public function refresh(string $id) {
    $artist = Artist::findOrFail($id);

    // Check if recently refreshed (prevent abuse)
    $lastRefresh = $artist->metrics()->latest()->first()?->last_refreshed;
    if ($lastRefresh && $lastRefresh->diffInMinutes(now()) < 60) {
        return response()->json([
            'message' => 'Artist data was recently refreshed. Please wait.',
            'next_available_at' => $lastRefresh->addHour(),
        ], 429);
    }

    // Dispatch async enrichment job
    \App\Jobs\EnrichArtistJob::dispatch($artist);

    return response()->json([
        'message' => 'Artist data refresh initiated',
        'artist_id' => $artist->id,
    ], 202);
}
```

**Human Oversight:**
- Approve rate limiting strategy (1 hour cooldown)
- Review abuse prevention
- Test queue processing
- Validate response messages

**Acceptance Criteria:**
- [ ] Queues background refresh job
- [ ] Returns 202 Accepted immediately
- [ ] Rate limits per artist (1/hour)
- [ ] Logs refresh requests
- [ ] Proper error handling

---

## Day 2: Frontend - Browse and Search

### Task 20: UI Component Library Setup
**Priority:** High
**Tags:** frontend, setup
**Dependencies:** Project Setup

**Business Context:**
Establish a consistent UI component library for rapid development and professional appearance.

**Technical Implementation:**

**AI Agent Tasks:**
```bash
# Install Tailwind CSS (recommended for utility-first styling)
cd artist-tree-frontend
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p

# Install additional UI components
npm install @headlessui/vue @heroicons/vue
npm install vue-router pinia axios
```

**Tailwind Configuration:**
```javascript
// tailwind.config.js
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#6366f1', // Indigo
        secondary: '#8b5cf6', // Purple
        accent: '#ec4899', // Pink
      }
    },
  },
  plugins: [],
}
```

**Main App Structure:**
```vue
<!-- src/App.vue -->
<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <RouterView />
  </div>
</template>
```

**Human Oversight:**
- Approve UI library choice (Tailwind vs Vuetify vs PrimeVue)
- Review color scheme
- Validate accessibility standards
- Approve component patterns

**Acceptance Criteria:**
- [ ] Tailwind CSS configured and working
- [ ] Design system colors defined
- [ ] Router configured
- [ ] Pinia store setup
- [ ] Axios configured for API calls

---

### Task 21: Artist Table Component
**Priority:** High
**Tags:** frontend, components
**Dependencies:** UI Component Library Setup

**Business Context:**
Display a sortable, filterable table of artists with their metrics for easy browsing.

**Technical Implementation:**

**AI Agent Tasks:**
```vue
<!-- src/components/ArtistTable.vue -->
<script setup>
import { ref, onMounted, computed } from 'vue'
import { useArtistStore } from '@/stores/artistStore'
import TierBadge from './TierBadge.vue'
import { formatNumber } from '@/utils/formatters'

const artistStore = useArtistStore()
const isLoading = ref(false)
const sortBy = ref('name')
const sortOrder = ref('asc')

onMounted(async () => {
  isLoading.value = true
  await artistStore.fetchArtists()
  isLoading.value = false
})

const sortedArtists = computed(() => {
  const artists = [...artistStore.artists]
  return artists.sort((a, b) => {
    let aVal = sortBy.value === 'score'
      ? a.metrics.score
      : a[sortBy.value]
    let bVal = sortBy.value === 'score'
      ? b.metrics.score
      : b[sortBy.value]

    if (sortOrder.value === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })
})

const handleSort = (column) => {
  if (sortBy.value === column) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = column
    sortOrder.value = 'desc'
  }
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="min-w-full bg-white shadow-md rounded-lg">
      <thead class="bg-gray-100">
        <tr>
          <th @click="handleSort('name')"
              class="px-6 py-3 text-left cursor-pointer hover:bg-gray-200">
            Artist Name
          </th>
          <th class="px-6 py-3 text-left">Genre</th>
          <th @click="handleSort('score')"
              class="px-6 py-3 text-left cursor-pointer hover:bg-gray-200">
            Score
          </th>
          <th class="px-6 py-3 text-left">Tier</th>
          <th class="px-6 py-3 text-left">Spotify Listeners</th>
          <th class="px-6 py-3 text-left">YouTube Subscribers</th>
          <th class="px-6 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody v-if="!isLoading">
        <tr v-for="artist in sortedArtists"
            :key="artist.id"
            class="border-t hover:bg-gray-50">
          <td class="px-6 py-4">
            <div class="flex items-center">
              <img :src="artist.image_url"
                   :alt="artist.name"
                   class="w-10 h-10 rounded-full mr-3"
                   v-if="artist.image_url" />
              <span class="font-medium">{{ artist.name }}</span>
            </div>
          </td>
          <td class="px-6 py-4 text-sm text-gray-600">
            {{ artist.genre?.join(', ') || 'N/A' }}
          </td>
          <td class="px-6 py-4 font-bold">
            {{ artist.metrics.score }}
          </td>
          <td class="px-6 py-4">
            <TierBadge :tier="artist.metrics.tier" />
          </td>
          <td class="px-6 py-4 text-sm">
            {{ formatNumber(artist.metrics.spotify_monthly_listeners) }}
          </td>
          <td class="px-6 py-4 text-sm">
            {{ formatNumber(artist.metrics.youtube_subscribers) }}
          </td>
          <td class="px-6 py-4">
            <button @click="viewDetails(artist.id)"
                    class="text-primary hover:underline">
              View
            </button>
          </td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr>
          <td colspan="7" class="px-6 py-8 text-center text-gray-500">
            Loading artists...
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

**Pinia Store:**
```javascript
// src/stores/artistStore.js
import { defineStore } from 'pinia'
import axios from 'axios'

export const useArtistStore = defineStore('artist', {
  state: () => ({
    artists: [],
    currentArtist: null,
  }),

  actions: {
    async fetchArtists(page = 1) {
      const response = await axios.get(`/api/artists?page=${page}`)
      this.artists = response.data.data
    },

    async fetchArtist(id) {
      const response = await axios.get(`/api/artists/${id}`)
      this.currentArtist = response.data.data
    },

    async searchArtists(query) {
      const response = await axios.get(`/api/artists/search?q=${query}`)
      return response.data.data
    },
  },
})
```

**Human Oversight:**
- Review table design and UX
- Test sorting functionality
- Validate responsive behavior
- Approve column selection

**Acceptance Criteria:**
- [ ] Displays artists in table format
- [ ] Sortable by name, score
- [ ] Shows artist images
- [ ] Loading state implemented
- [ ] Responsive design (mobile-friendly)

---

### Task 22: Format Numbers Helper
**Priority:** Low
**Tags:** frontend, utilities
**Dependencies:** Artist Table Component

**Business Context:**
Format large numbers (millions, thousands) for readability in the UI.

**Technical Implementation:**

**AI Agent Tasks:**
```javascript
// src/utils/formatters.js
export function formatNumber(num) {
  if (!num) return 'N/A'

  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M'
  } else if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K'
  }

  return num.toString()
}

export function formatScore(score) {
  return Math.round(score * 10) / 10
}

export function formatDate(dateString) {
  if (!dateString) return 'Never'

  const date = new Date(dateString)
  const now = new Date()
  const diffHours = (now - date) / (1000 * 60 * 60)

  if (diffHours < 24) {
    return `${Math.floor(diffHours)} hours ago`
  } else {
    return date.toLocaleDateString()
  }
}
```

**Human Oversight:**
- Approve formatting conventions
- Review localization needs
- Test with edge cases

**Acceptance Criteria:**
- [ ] Formats millions as "M"
- [ ] Formats thousands as "K"
- [ ] Handles null/undefined values
- [ ] Unit tests for formatters

---

### Task 23: Tier Badge Component
**Priority:** Medium
**Tags:** frontend, components
**Dependencies:** Artist Table Component

**Business Context:**
Visually distinguish artist tiers with color-coded badges.

**Technical Implementation:**

**AI Agent Tasks:**
```vue
<!-- src/components/TierBadge.vue -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
  tier: {
    type: String,
    required: true,
    validator: (value) => ['headliner', 'sub_headliner', 'mid_tier', 'undercard'].includes(value)
  }
})

const tierConfig = computed(() => {
  const configs = {
    headliner: {
      label: 'Headliner',
      classes: 'bg-purple-100 text-purple-800 border-purple-300'
    },
    sub_headliner: {
      label: 'Sub-Headliner',
      classes: 'bg-blue-100 text-blue-800 border-blue-300'
    },
    mid_tier: {
      label: 'Mid-Tier',
      classes: 'bg-green-100 text-green-800 border-green-300'
    },
    undercard: {
      label: 'Undercard',
      classes: 'bg-gray-100 text-gray-800 border-gray-300'
    }
  }

  return configs[props.tier] || configs.undercard
})
</script>

<template>
  <span :class="[
    'px-3 py-1 rounded-full text-xs font-semibold border',
    tierConfig.classes
  ]">
    {{ tierConfig.label }}
  </span>
</template>
```

**Human Oversight:**
- Approve color scheme for tiers
- Validate accessibility (contrast ratios)
- Review badge styling

**Acceptance Criteria:**
- [ ] Color-coded by tier
- [ ] Readable text
- [ ] WCAG AA compliant contrast
- [ ] Responsive sizing

---

### Task 24: Debounce Hook
**Priority:** Medium
**Tags:** frontend, utilities
**Dependencies:** -

**Business Context:**
Implement debouncing for search input to reduce API calls and improve performance.

**Technical Implementation:**

**AI Agent Tasks:**
```javascript
// src/composables/useDebounce.js
import { ref, watch } from 'vue'

export function useDebounce(value, delay = 300) {
  const debouncedValue = ref(value.value)
  let timeout = null

  watch(value, (newValue) => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
      debouncedValue.value = newValue
    }, delay)
  })

  return debouncedValue
}
```

**Human Oversight:**
- Approve debounce delay (300ms)
- Test user experience
- Validate performance improvement

**Acceptance Criteria:**
- [ ] Delays execution by specified time
- [ ] Cancels previous timers
- [ ] Reusable composable
- [ ] Works with reactive refs

---

### Task 25: Artist Search Component
**Priority:** High
**Tags:** frontend, components, search
**Dependencies:** Debounce Hook

**Business Context:**
Provide autocomplete search functionality for finding artists quickly.

**Technical Implementation:**

**AI Agent Tasks:**
```vue
<!-- src/components/ArtistSearch.vue -->
<script setup>
import { ref, watch } from 'vue'
import { useDebounce } from '@/composables/useDebounce'
import { useArtistStore } from '@/stores/artistStore'

const artistStore = useArtistStore()
const searchQuery = ref('')
const debouncedQuery = useDebounce(searchQuery)
const searchResults = ref([])
const isSearching = ref(false)

const emit = defineEmits(['select'])

watch(debouncedQuery, async (newQuery) => {
  if (newQuery.length < 2) {
    searchResults.value = []
    return
  }

  isSearching.value = true
  try {
    searchResults.value = await artistStore.searchArtists(newQuery)
  } catch (error) {
    console.error('Search failed:', error)
  } finally {
    isSearching.value = false
  }
})

const selectArtist = (artist) => {
  emit('select', artist)
  searchQuery.value = ''
  searchResults.value = []
}
</script>

<template>
  <div class="relative">
    <input
      v-model="searchQuery"
      type="text"
      placeholder="Search for artists..."
      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
    />

    <div v-if="isSearching"
         class="absolute right-3 top-3">
      <svg class="animate-spin h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-if="searchResults.length > 0"
         class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-96 overflow-y-auto">
      <div v-for="artist in searchResults"
           :key="artist.id"
           @click="selectArtist(artist)"
           class="px-4 py-3 hover:bg-gray-50 cursor-pointer flex items-center">
        <img v-if="artist.image_url"
             :src="artist.image_url"
             :alt="artist.name"
             class="w-10 h-10 rounded-full mr-3" />
        <div class="flex-1">
          <div class="font-medium">{{ artist.name }}</div>
          <div class="text-sm text-gray-500">
            {{ artist.genre?.slice(0, 2).join(', ') }}
          </div>
        </div>
        <div class="text-sm font-semibold text-gray-700">
          Score: {{ artist.metrics.score }}
        </div>
      </div>
    </div>

    <div v-if="searchQuery.length >= 2 && searchResults.length === 0 && !isSearching"
         class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg p-4 text-center text-gray-500">
      No artists found
    </div>
  </div>
</template>
```

**Human Oversight:**
- Test search UX and responsiveness
- Review autocomplete behavior
- Validate keyboard navigation
- Approve styling

**Acceptance Criteria:**
- [ ] Debounced search (300ms)
- [ ] Shows results dropdown
- [ ] Displays artist images
- [ ] Click to select
- [ ] Keyboard accessible
- [ ] Loading indicator

---

### Task 26-28: Page Layouts (Artist Detail, Metrics Display, Home Dashboard)
**Priority:** High/Medium
**Tags:** frontend, pages
**Dependencies:** Artist Table Component, Artist Search Component

**Business Context:**
Create main application pages for browsing artists, viewing details, and dashboard overview.

**Technical Implementation:**

**AI Agent Tasks:**
```vue
<!-- src/views/HomePage.vue -->
<script setup>
import { ref } from 'vue'
import ArtistTable from '@/components/ArtistTable.vue'
import ArtistSearch from '@/components/ArtistSearch.vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const handleArtistSelect = (artist) => {
  router.push(`/artists/${artist.id}`)
}
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <div class="mb-8">
      <h1 class="text-4xl font-bold text-gray-900 mb-2">
        Artist-Tree
      </h1>
      <p class="text-gray-600">
        Discover and manage festival lineups with data-driven insights
      </p>
    </div>

    <div class="mb-6">
      <ArtistSearch @select="handleArtistSelect" />
    </div>

    <div class="bg-white rounded-lg shadow">
      <div class="px-6 py-4 border-b">
        <h2 class="text-2xl font-semibold">All Artists</h2>
      </div>
      <ArtistTable />
    </div>
  </div>
</template>

<!-- src/views/ArtistDetailPage.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useArtistStore } from '@/stores/artistStore'
import TierBadge from '@/components/TierBadge.vue'
import { formatNumber, formatDate } from '@/utils/formatters'

const route = useRoute()
const artistStore = useArtistStore()
const isLoading = ref(true)

onMounted(async () => {
  await artistStore.fetchArtist(route.params.id)
  isLoading.value = false
})
</script>

<template>
  <div class="container mx-auto px-4 py-8" v-if="!isLoading && artistStore.currentArtist">
    <div class="bg-white rounded-lg shadow-lg p-8">
      <div class="flex items-start mb-8">
        <img :src="artistStore.currentArtist.image_url"
             :alt="artistStore.currentArtist.name"
             class="w-32 h-32 rounded-lg mr-6" />
        <div class="flex-1">
          <h1 class="text-4xl font-bold mb-2">
            {{ artistStore.currentArtist.name }}
          </h1>
          <div class="flex items-center gap-4 mb-4">
            <TierBadge :tier="artistStore.currentArtist.current_metrics.tier" />
            <span class="text-2xl font-bold text-primary">
              Score: {{ artistStore.currentArtist.current_metrics.score }}
            </span>
          </div>
          <div class="text-gray-600">
            {{ artistStore.currentArtist.genre?.join(' • ') }}
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-6 mb-8">
        <div class="bg-purple-50 rounded-lg p-6">
          <div class="text-sm text-gray-600 mb-1">Spotify Listeners</div>
          <div class="text-3xl font-bold text-purple-700">
            {{ formatNumber(artistStore.currentArtist.current_metrics.spotify_monthly_listeners) }}
          </div>
        </div>
        <div class="bg-blue-50 rounded-lg p-6">
          <div class="text-sm text-gray-600 mb-1">YouTube Subscribers</div>
          <div class="text-3xl font-bold text-blue-700">
            {{ formatNumber(artistStore.currentArtist.current_metrics.youtube_subscribers) }}
          </div>
        </div>
        <div class="bg-green-50 rounded-lg p-6">
          <div class="text-sm text-gray-600 mb-1">Last Updated</div>
          <div class="text-lg font-semibold text-green-700">
            {{ formatDate(artistStore.currentArtist.current_metrics.last_refreshed) }}
          </div>
        </div>
      </div>

      <div v-if="artistStore.currentArtist.metrics_history?.length > 0">
        <h2 class="text-2xl font-bold mb-4">Metrics History</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-gray-100">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Score</th>
                <th class="px-4 py-2 text-left">Spotify</th>
                <th class="px-4 py-2 text-left">YouTube</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(metric, index) in artistStore.currentArtist.metrics_history"
                  :key="index"
                  class="border-t">
                <td class="px-4 py-2">{{ formatDate(metric.recorded_at) }}</td>
                <td class="px-4 py-2 font-semibold">{{ metric.score }}</td>
                <td class="px-4 py-2">{{ formatNumber(metric.spotify_monthly_listeners) }}</td>
                <td class="px-4 py-2">{{ formatNumber(metric.youtube_subscribers) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="container mx-auto px-4 py-8 text-center">
    Loading artist details...
  </div>
</template>
```

**Router Configuration:**
```javascript
// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '@/views/HomePage.vue'
import ArtistDetailPage from '@/views/ArtistDetailPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomePage
    },
    {
      path: '/artists/:id',
      name: 'artist-detail',
      component: ArtistDetailPage
    }
  ]
})

export default router
```

**Human Oversight:**
- Review page layouts and UX
- Test navigation flow
- Validate responsive design
- Approve design system

**Acceptance Criteria:**
- [ ] Home page displays artist table
- [ ] Artist detail page shows full metrics
- [ ] Navigation works correctly
- [ ] Loading states implemented
- [ ] Responsive on mobile/tablet

---

## Day 3: Lineup Builder and Tiers

### Task 29-32: Lineup API Endpoints (Create, List, Get, Add/Remove Artists)
**Priority:** High
**Tags:** backend, api, lineup
**Dependencies:** Tier Calculation Logic

**Business Context:**
Provide CRUD endpoints for lineup management and artist assignment with automatic tier calculation.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// routes/api.php
Route::prefix('lineups')->group(function () {
    Route::get('/', [LineupController::class, 'index']);
    Route::post('/', [LineupController::class, 'store']);
    Route::get('/{id}', [LineupController::class, 'show']);
    Route::put('/{id}', [LineupController::class, 'update']);
    Route::delete('/{id}', [LineupController::class, 'destroy']);
    Route::post('/{id}/artists', [LineupController::class, 'addArtist']);
    Route::delete('/{id}/artists/{artistId}', [LineupController::class, 'removeArtist']);
});

// app/Http/Controllers/LineupController.php
namespace App\Http\Controllers;

use App\Models\Lineup;
use App\Models\Artist;
use App\Services\TierCalculationService;
use Illuminate\Http\Request;

class LineupController extends Controller {
    public function __construct(
        private TierCalculationService $tierService
    ) {}

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|uuid',
        ]);

        $lineup = Lineup::create($validated);

        return response()->json($lineup, 201);
    }

    public function show(string $id) {
        $lineup = Lineup::with(['artists.metrics' => function($q) {
            $q->latest();
        }])->findOrFail($id);

        return response()->json([
            'id' => $lineup->id,
            'name' => $lineup->name,
            'description' => $lineup->description,
            'artists' => $lineup->artists->map(function($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image_url' => $artist->image_url,
                    'score' => $artist->metrics->first()?->score ?? 0,
                    'tier' => $artist->pivot->tier,
                    'position' => $artist->pivot->position,
                ];
            }),
            'created_at' => $lineup->created_at,
        ]);
    }

    public function addArtist(Request $request, string $id) {
        $validated = $request->validate([
            'artist_id' => 'required|uuid|exists:artists,id',
        ]);

        $lineup = Lineup::findOrFail($id);
        $artist = Artist::with('metrics')->findOrFail($validated['artist_id']);

        // Check if artist already in lineup
        if ($lineup->artists()->where('artist_id', $artist->id)->exists()) {
            return response()->json([
                'message' => 'Artist already in lineup'
            ], 409);
        }

        // Get current max position
        $maxPosition = $lineup->artists()->max('position') ?? 0;

        // Add artist with temporary tier
        $lineup->artists()->attach($artist->id, [
            'position' => $maxPosition + 1,
            'tier' => 'undercard', // Will be recalculated
        ]);

        // Recalculate all tiers
        $this->tierService->recalculateTiers($lineup);

        return response()->json([
            'message' => 'Artist added to lineup',
            'lineup' => $this->show($id)->getData(),
        ]);
    }

    public function removeArtist(string $id, string $artistId) {
        $lineup = Lineup::findOrFail($id);

        $lineup->artists()->detach($artistId);

        // Recalculate tiers after removal
        $this->tierService->recalculateTiers($lineup);

        return response()->json([
            'message' => 'Artist removed from lineup'
        ]);
    }
}
```

**Human Oversight:**
- Review lineup creation flow
- Test tier recalculation performance
- Validate conflict handling
- Approve API responses

**Acceptance Criteria:**
- [ ] Create lineup endpoint works
- [ ] List lineups by user
- [ ] Add artist updates tiers automatically
- [ ] Remove artist recalculates tiers
- [ ] Proper validation and errors

---

### Task 33: Tier Calculation Logic
**Priority:** High
**Tags:** backend, algorithm
**Dependencies:** API - Get Lineup with Artists

**Business Context:**
Dynamically assign tiers based on relative scores within a specific lineup.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// app/Services/TierCalculationService.php
namespace App\Services;

use App\Models\Lineup;

class TierCalculationService {
    public function recalculateTiers(Lineup $lineup): void {
        $artists = $lineup->artists()
            ->with(['metrics' => function($q) {
                $q->latest();
            }])
            ->get()
            ->sortByDesc(function($artist) {
                return $artist->metrics->first()?->score ?? 0;
            });

        $totalArtists = $artists->count();

        if ($totalArtists === 0) {
            return;
        }

        // Calculate tier thresholds based on percentiles
        $headlinerCount = max(1, ceil($totalArtists * 0.10)); // Top 10%
        $subHeadlinerCount = ceil($totalArtists * 0.20); // Next 20%
        $midTierCount = ceil($totalArtists * 0.40); // Next 40%
        // Remaining 30% are undercard

        $position = 0;
        foreach ($artists as $artist) {
            $position++;

            $tier = match(true) {
                $position <= $headlinerCount => 'headliner',
                $position <= ($headlinerCount + $subHeadlinerCount) => 'sub_headliner',
                $position <= ($headlinerCount + $subHeadlinerCount + $midTierCount) => 'mid_tier',
                default => 'undercard',
            };

            // Update pivot table
            $lineup->artists()->updateExistingPivot($artist->id, [
                'tier' => $tier,
                'position' => $position,
            ]);
        }

        \Log::info("Recalculated tiers for lineup", [
            'lineup_id' => $lineup->id,
            'total_artists' => $totalArtists,
            'headliners' => $headlinerCount,
        ]);
    }

    public function getTierDistribution(Lineup $lineup): array {
        $distribution = $lineup->artists()
            ->selectRaw('tier, COUNT(*) as count')
            ->groupBy('tier')
            ->pluck('count', 'tier')
            ->toArray();

        return [
            'headliner' => $distribution['headliner'] ?? 0,
            'sub_headliner' => $distribution['sub_headliner'] ?? 0,
            'mid_tier' => $distribution['mid_tier'] ?? 0,
            'undercard' => $distribution['undercard'] ?? 0,
        ];
    }
}
```

**Human Oversight:**
- Review tier distribution algorithm
- Validate percentile calculations
- Test with various lineup sizes
- Approve tier assignment logic

**Acceptance Criteria:**
- [ ] Tiers assigned based on relative scores
- [ ] Always at least 1 headliner
- [ ] Percentiles work with any lineup size
- [ ] Updates all artists in lineup
- [ ] Performance tested with 100+ artists

---

### Task 34-37: Lineup Builder Frontend Components
**Priority:** High/Medium
**Tags:** frontend, components, lineup
**Dependencies:** Lineup API Endpoints

**Business Context:**
Create interactive UI for building and managing festival lineups with drag-and-drop functionality.

**Technical Implementation:**

**AI Agent Tasks:**
```vue
<!-- src/views/LineupBuilderPage.vue -->
<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useLineupStore } from '@/stores/lineupStore'
import ArtistSearch from '@/components/ArtistSearch.vue'
import TierColumn from '@/components/TierColumn.vue'

const route = useRoute()
const lineupStore = useLineupStore()
const isLoading = ref(true)

onMounted(async () => {
  await lineupStore.fetchLineup(route.params.id)
  isLoading.value = false
})

const tierGroups = computed(() => {
  const groups = {
    headliner: [],
    sub_headliner: [],
    mid_tier: [],
    undercard: []
  }

  lineupStore.currentLineup?.artists.forEach(artist => {
    groups[artist.tier].push(artist)
  })

  return groups
})

const handleAddArtist = async (artist) => {
  await lineupStore.addArtistToLineup(route.params.id, artist.id)
}

const handleRemoveArtist = async (artistId) => {
  await lineupStore.removeArtistFromLineup(route.params.id, artistId)
}
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <div class="mb-8">
      <h1 class="text-3xl font-bold mb-2">
        {{ lineupStore.currentLineup?.name }}
      </h1>
      <p class="text-gray-600">
        {{ lineupStore.currentLineup?.description }}
      </p>
    </div>

    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Add Artist to Lineup
      </label>
      <ArtistSearch @select="handleAddArtist" />
    </div>

    <div class="grid grid-cols-4 gap-4">
      <TierColumn
        title="Headliners"
        tier="headliner"
        :artists="tierGroups.headliner"
        @remove="handleRemoveArtist"
      />
      <TierColumn
        title="Sub-Headliners"
        tier="sub_headliner"
        :artists="tierGroups.sub_headliner"
        @remove="handleRemoveArtist"
      />
      <TierColumn
        title="Mid-Tier"
        tier="mid_tier"
        :artists="tierGroups.mid_tier"
        @remove="handleRemoveArtist"
      />
      <TierColumn
        title="Undercard"
        tier="undercard"
        :artists="tierGroups.undercard"
        @remove="handleRemoveArtist"
      />
    </div>
  </div>
</template>

<!-- src/components/TierColumn.vue -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: String,
  tier: String,
  artists: Array
})

const emit = defineEmits(['remove'])

const tierColor = computed(() => {
  const colors = {
    headliner: 'border-purple-500 bg-purple-50',
    sub_headliner: 'border-blue-500 bg-blue-50',
    mid_tier: 'border-green-500 bg-green-50',
    undercard: 'border-gray-500 bg-gray-50'
  }
  return colors[props.tier]
})
</script>

<template>
  <div :class="['border-2 rounded-lg p-4 min-h-96', tierColor]">
    <h3 class="font-bold text-lg mb-4">
      {{ title }} ({{ artists.length }})
    </h3>

    <div class="space-y-2">
      <div v-for="artist in artists"
           :key="artist.id"
           class="bg-white rounded-lg p-3 shadow-sm flex items-center justify-between">
        <div class="flex items-center flex-1">
          <img :src="artist.image_url"
               :alt="artist.name"
               class="w-10 h-10 rounded mr-3" />
          <div>
            <div class="font-medium">{{ artist.name }}</div>
            <div class="text-sm text-gray-500">
              Score: {{ artist.score }}
            </div>
          </div>
        </div>
        <button @click="emit('remove', artist.id)"
                class="text-red-500 hover:text-red-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<!-- src/stores/lineupStore.js -->
import { defineStore } from 'pinia'
import axios from 'axios'

export const useLineupStore = defineStore('lineup', {
  state: () => ({
    lineups: [],
    currentLineup: null,
  }),

  actions: {
    async fetchLineups() {
      const response = await axios.get('/api/lineups')
      this.lineups = response.data
    },

    async fetchLineup(id) {
      const response = await axios.get(`/api/lineups/${id}`)
      this.currentLineup = response.data
    },

    async createLineup(data) {
      const response = await axios.post('/api/lineups', data)
      this.lineups.push(response.data)
      return response.data
    },

    async addArtistToLineup(lineupId, artistId) {
      const response = await axios.post(`/api/lineups/${lineupId}/artists`, {
        artist_id: artistId
      })
      this.currentLineup = response.data.lineup
    },

    async removeArtistFromLineup(lineupId, artistId) {
      await axios.delete(`/api/lineups/${lineupId}/artists/${artistId}`)
      await this.fetchLineup(lineupId) // Refresh to get updated tiers
    },
  },
})
```

**Human Oversight:**
- Review lineup builder UX
- Test tier visualization
- Validate add/remove flow
- Approve drag-and-drop (future enhancement)

**Acceptance Criteria:**
- [ ] Displays artists in tier columns
- [ ] Add artist updates tiers in real-time
- [ ] Remove artist recalculates tiers
- [ ] Visual distinction between tiers
- [ ] Responsive layout

---

## Day 4: Testing and Deployment

### Task 38-40: Unit and Integration Tests
**Priority:** High
**Tags:** testing, quality-assurance
**Dependencies:** Various

**Business Context:**
Ensure code quality and prevent regressions with comprehensive test coverage.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// tests/Feature/LineupApiTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Lineup;
use App\Models\Artist;
use App\Models\ArtistMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LineupApiTest extends TestCase {
    use RefreshDatabase;

    public function test_can_create_lineup(): void {
        $response = $this->postJson('/api/lineups', [
            'name' => 'Coachella 2025',
            'description' => 'Test lineup',
            'user_id' => fake()->uuid(),
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['id', 'name', 'description']);
    }

    public function test_can_add_artist_to_lineup(): void {
        $lineup = Lineup::factory()->create();
        $artist = Artist::factory()->create();
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'score' => 85
        ]);

        $response = $this->postJson("/api/lineups/{$lineup->id}/artists", [
            'artist_id' => $artist->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('lineup_artists', [
            'lineup_id' => $lineup->id,
            'artist_id' => $artist->id,
        ]);
    }

    public function test_tier_recalculation_on_artist_addition(): void {
        $lineup = Lineup::factory()->create();

        // Create artists with different scores
        $headliner = Artist::factory()->create();
        ArtistMetric::factory()->create([
            'artist_id' => $headliner->id,
            'score' => 95
        ]);

        $undercard = Artist::factory()->create();
        ArtistMetric::factory()->create([
            'artist_id' => $undercard->id,
            'score' => 30
        ]);

        // Add both artists
        $this->postJson("/api/lineups/{$lineup->id}/artists", [
            'artist_id' => $headliner->id
        ]);
        $this->postJson("/api/lineups/{$lineup->id}/artists", [
            'artist_id' => $undercard->id
        ]);

        // Check tiers
        $this->assertDatabaseHas('lineup_artists', [
            'artist_id' => $headliner->id,
            'tier' => 'headliner'
        ]);
        $this->assertDatabaseHas('lineup_artists', [
            'artist_id' => $undercard->id,
            'tier' => 'undercard'
        ]);
    }
}

// tests/Unit/TierCalculationServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Lineup;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Services\TierCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TierCalculationServiceTest extends TestCase {
    use RefreshDatabase;

    public function test_assigns_tiers_correctly_for_10_artists(): void {
        $service = new TierCalculationService();
        $lineup = Lineup::factory()->create();

        // Create 10 artists with descending scores
        for ($i = 0; $i < 10; $i++) {
            $artist = Artist::factory()->create();
            ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'score' => 100 - ($i * 10)
            ]);
            $lineup->artists()->attach($artist->id);
        }

        $service->recalculateTiers($lineup);

        $distribution = $service->getTierDistribution($lineup);

        // Top 10% = 1 headliner
        $this->assertEquals(1, $distribution['headliner']);
        // Next 20% = 2 sub-headliners
        $this->assertEquals(2, $distribution['sub_headliner']);
        // Next 40% = 4 mid-tier
        $this->assertEquals(4, $distribution['mid_tier']);
        // Bottom 30% = 3 undercard
        $this->assertEquals(3, $distribution['undercard']);
    }
}
```

**Vue Component Tests:**
```javascript
// tests/unit/ArtistSearch.spec.js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import ArtistSearch from '@/components/ArtistSearch.vue'
import { createPinia } from 'pinia'

describe('ArtistSearch', () => {
  it('renders search input', () => {
    const wrapper = mount(ArtistSearch, {
      global: {
        plugins: [createPinia()]
      }
    })

    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('emits select event when artist clicked', async () => {
    const wrapper = mount(ArtistSearch, {
      global: {
        plugins: [createPinia()]
      }
    })

    const artist = { id: '1', name: 'Test Artist' }
    await wrapper.vm.selectArtist(artist)

    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')[0]).toEqual([artist])
  })
})
```

**Human Oversight:**
- Review test coverage reports
- Approve test strategy
- Validate edge cases
- Sign off on test suite

**Acceptance Criteria:**
- [ ] Backend test coverage > 80%
- [ ] Frontend test coverage > 70%
- [ ] All critical paths tested
- [ ] Integration tests pass
- [ ] CI pipeline runs tests

---

### Task 41-44: Performance Optimization
**Priority:** Medium
**Tags:** optimization, performance
**Dependencies:** Various

**Business Context:**
Ensure application performs well under load with optimized queries and caching.

**Technical Implementation:**

**AI Agent Tasks:**
```php
// Database Optimization - Add indexes
// supabase/migrations/005_performance_indexes.sql
CREATE INDEX CONCURRENTLY idx_artist_metrics_score_desc
ON artist_metrics(score DESC, last_refreshed DESC);

CREATE INDEX CONCURRENTLY idx_lineup_artists_composite
ON lineup_artists(lineup_id, tier, position);

-- Materialized view for popular artists
CREATE MATERIALIZED VIEW popular_artists AS
SELECT
    a.id,
    a.name,
    a.image_url,
    am.score,
    am.spotify_monthly_listeners,
    am.last_refreshed
FROM artists a
JOIN artist_metrics am ON a.id = am.artist_id
WHERE am.id IN (
    SELECT DISTINCT ON (artist_id) id
    FROM artist_metrics
    ORDER BY artist_id, created_at DESC
)
AND am.score > 60
ORDER BY am.score DESC;

CREATE UNIQUE INDEX ON popular_artists (id);

// app/Http/Controllers/ArtistController.php - Eager loading
public function index(Request $request) {
    $artists = Artist::with(['metrics' => function($query) {
        $query->latest()->limit(1); // Only get latest metric
    }])
    ->select(['id', 'name', 'image_url', 'genre', 'spotify_id']) // Only needed fields
    ->orderBy('name')
    ->paginate(20);

    return ArtistResource::collection($artists);
}

// app/Http/Middleware/CacheResponse.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CacheResponse {
    public function handle($request, Closure $next, $ttl = 300) {
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key = 'route:' . md5($request->fullUrl());

        return Cache::remember($key, $ttl, function() use ($request, $next) {
            return $next($request);
        });
    }
}
```

**Vue Optimization:**
```vue
<!-- Use v-memo for expensive renders -->
<template>
  <div v-for="artist in artists" :key="artist.id" v-memo="[artist.score]">
    <!-- Component only re-renders when score changes -->
  </div>
</template>

<script setup>
import { computed } from 'vue'

// Memoize expensive computations
const sortedArtists = computed(() => {
  // Will only recalculate when dependencies change
  return artists.value.sort((a, b) => b.score - a.score)
})
</script>
```

**Human Oversight:**
- Review query performance with EXPLAIN
- Monitor API response times
- Test with production-scale data
- Approve caching strategy

**Acceptance Criteria:**
- [ ] Database queries optimized
- [ ] N+1 queries eliminated
- [ ] Response times < 500ms (p95)
- [ ] Frontend bundle size < 500KB
- [ ] Lazy loading implemented

---

### Task 45-48: Laravel Cloud Deployment Setup
**Priority:** High
**Tags:** deployment, devops, laravel-cloud
**Dependencies:** Pre-Deployment Checklist

**Business Context:**
Deploy monolithic Laravel application (Inertia + API) to Laravel Cloud with proper environment configuration and monitoring.

**Technical Implementation:**

**Laravel Cloud Setup:**
```bash
# Install Laravel Cloud CLI
composer global require laravel/cloud

# Login to Laravel Cloud
php cloud login

# Create new project
php cloud create artist-tree

# Link local project to Laravel Cloud
php cloud link artist-tree

# Configure environment variables via Laravel Cloud dashboard
# - SPOTIFY_CLIENT_ID
# - SPOTIFY_CLIENT_SECRET
# - YOUTUBE_API_KEY
# - APP_KEY (auto-generated)
# - Database credentials (auto-configured by Laravel Cloud)

# Deploy to production
php cloud deploy
```

**CI/CD Pipeline (GitHub Actions):**
```yaml
# .github/workflows/deploy.yml
name: Deploy to Laravel Cloud

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'

      - name: Install Dependencies
        run: |
          composer install --no-interaction --prefer-dist
          npm install

      - name: Run Tests
        run: php artisan test

      - name: Build Assets
        run: npm run build

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Deploy to Laravel Cloud
        run: |
          composer global require laravel/cloud
          php cloud deploy --token=${{ secrets.LARAVEL_CLOUD_TOKEN }}
```

**Environment Variables (Laravel Cloud Dashboard):**
```bash
# Application
APP_NAME="Artist Tree"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://artist-tree.laravel.cloud
APP_KEY=${AUTO_GENERATED}

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${LARAVEL_CLOUD_DB_NAME}
DB_USERNAME=${LARAVEL_CLOUD_DB_USER}
DB_PASSWORD=${LARAVEL_CLOUD_DB_PASSWORD}

# Cache & Queue (Laravel Cloud Redis)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=${LARAVEL_CLOUD_REDIS_HOST}
REDIS_PASSWORD=${LARAVEL_CLOUD_REDIS_PASSWORD}

# External APIs
SPOTIFY_CLIENT_ID=${YOUR_SPOTIFY_CLIENT_ID}
SPOTIFY_CLIENT_SECRET=${YOUR_SPOTIFY_CLIENT_SECRET}
YOUTUBE_API_KEY=${YOUR_YOUTUBE_API_KEY}

# Session (important for Inertia)
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

**Deployment Commands:**
```bash
# Manual deployment
php cloud deploy

# Run migrations after deployment
php cloud artisan migrate --force

# Clear caches
php cloud artisan optimize:clear
php cloud artisan config:cache
php cloud artisan route:cache
php cloud artisan view:cache

# Check deployment status
php cloud status

# View logs
php cloud logs
```

**Human Oversight:**
- Create Laravel Cloud account and project
- Provide production API credentials (Spotify, YouTube)
- Review security settings (HTTPS, SSL)
- Test production environment post-deployment
- Approve go-live checklist
- Set up monitoring/alerting in Laravel Cloud dashboard

**Acceptance Criteria:**
- [ ] Application deployed to Laravel Cloud
- [ ] Custom domain configured (optional) or using .laravel.cloud subdomain
- [ ] Environment variables configured in Laravel Cloud dashboard
- [ ] SSL certificate active (automatic with Laravel Cloud)
- [ ] Database migrations run successfully
- [ ] Inertia pages load correctly in production
- [ ] API endpoints responding with valid JSON
- [ ] Background jobs/queues processing (if configured)
- [ ] Monitoring/logging configured via Laravel Cloud dashboard
- [ ] Cache working (Redis on Laravel Cloud)

---

## Day 5: Customer Validation

### Task 49: Documentation - API Setup Guide
**Priority:** Medium
**Tags:** documentation
**Dependencies:** Laravel Cloud Deployment Setup

**Business Context:**
Provide clear documentation for API usage, setup, and troubleshooting.

**Technical Implementation:**

**AI Agent Tasks:**
Create `docs/API_GUIDE.md`:

```markdown
# Artist-Tree API Guide

## Base URL
```
Production: https://api.artist-tree.com/api
Development: http://localhost:8000/api
```

## Authentication
Currently uses API keys (future: Laravel Sanctum)

## Endpoints

### Artists

#### List Artists
```http
GET /artists?page=1
```

Response:
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Artist Name",
      "genre": ["Electronic", "Dance"],
      "image_url": "https://...",
      "metrics": {
        "score": 85.5,
        "spotify_monthly_listeners": 5000000,
        "youtube_subscribers": 2000000,
        "tier": "headliner"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 100
  }
}
```

#### Search Artists
```http
GET /artists/search?q=query
```

#### Get Artist Details
```http
GET /artists/{id}
```

### Lineups

#### Create Lineup
```http
POST /lineups
Content-Type: application/json

{
  "name": "My Festival 2025",
  "description": "Summer music festival",
  "user_id": "uuid"
}
```

#### Add Artist to Lineup
```http
POST /lineups/{lineupId}/artists
Content-Type: application/json

{
  "artist_id": "uuid"
}
```

## Rate Limits
- 100 requests per minute per IP
- Artist refresh: 1 per hour per artist

## Error Codes
- 400: Bad Request
- 404: Not Found
- 429: Too Many Requests
- 500: Server Error
```

**Human Oversight:**
- Review documentation completeness
- Test API examples
- Validate accuracy
- Approve for publication

**Acceptance Criteria:**
- [ ] All endpoints documented
- [ ] Request/response examples provided
- [ ] Error handling explained
- [ ] Rate limits documented
- [ ] Setup instructions clear

---

### Task 50: Week 2 Roadmap Planning
**Priority:** High
**Tags:** planning, roadmap
**Dependencies:** Post-Demo Debrief and Analysis

**Business Context:**
Plan next iteration based on user feedback and initial deployment learnings.

**AI Agent Tasks:**
Create `docs/WEEK2_ROADMAP.md`:

```markdown
# Week 2 Roadmap

## User Feedback Priorities

### High Priority
1. **Drag-and-Drop Lineup Reordering**
   - Allow manual tier overrides
   - Save custom positions

2. **Export Functionality**
   - Export lineup as PDF
   - Export lineup as image
   - Share link generation

3. **User Authentication**
   - Implement Laravel Fortify/Breeze for authentication
   - User profile management
   - Save lineups to user account

### Medium Priority
4. **Advanced Filtering**
   - Filter by genre
   - Filter by score range
   - Filter by platform availability

5. **Metrics Visualization**
   - Score history charts
   - Tier distribution graphs
   - Comparison views

6. **Collaboration Features**
   - Share lineups with team
   - Comments on lineups
   - Version history

### Low Priority
7. **Mobile Optimization**
   - Touch-friendly interactions
   - Mobile-specific layouts

8. **Performance Enhancements**
   - Infinite scroll
   - Virtual scrolling for large lists
```

**Human Oversight:**
- Review and prioritize features
- Validate business value
- Approve development timeline
- Assign resources

**Acceptance Criteria:**
- [ ] Roadmap created with priorities
- [ ] User feedback incorporated
- [ ] Technical feasibility assessed
- [ ] Timeline estimated
- [ ] Stakeholder approval

---

## Priority Summary

| Priority | Count | Percentage |
|----------|-------|------------|
| High | 38 | 56% |
| Medium | 24 | 35% |
| Low | 6 | 9% |

## Collaboration Summary

**AI Agent Responsibilities:**
- Write all boilerplate code (models, controllers, services)
- Implement algorithms (scoring, tier calculation)
- Create Vue components with Composition API
- Write unit and integration tests
- Set up deployment configurations
- Generate documentation

**Human Oversight Required:**
- Provide API credentials (Spotify, YouTube, Laravel Cloud)
- Review and approve algorithms and business logic
- Test UX and provide design feedback
- Make final decisions on architecture choices
- Approve deployments to production
- Prioritize features and roadmap

---

**End of Task Breakdown**

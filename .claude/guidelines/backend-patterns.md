# Laravel Backend Implementation Patterns

## Artist-Tree Specific Patterns

This document contains Laravel implementation patterns specific to the Artist-Tree project. Always consult `.claude/guidelines/laravel-boost.md` for comprehensive Laravel framework guidelines.

---

## Service Class Pattern

All business logic MUST be implemented in service classes, not controllers.

### ArtistScoringService Implementation

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

### Thin Controller Pattern

Controllers must delegate to services and remain thin (10-15 lines max).

```php
// ✅ GOOD: Controller delegates to service
public function addArtist(Lineup $lineup, AddArtistToLineupRequest $request)
{
    $artist = Artist::findOrFail($request->artist_id);

    $this->tierCalculationService->addArtistToLineup($lineup, $artist);

    return LineupResource::make($lineup->fresh('artists'));
}

// ❌ BAD: Business logic in controller
public function addArtist(Lineup $lineup, Request $request)
{
    // 50 lines of tier calculation logic here
}
```

---

## Organization-Aware Service Pattern

Services that deal with scoring, metrics, or tiers MUST accept an `Organization` parameter.

**Why:** Different organizations have different metric weights and business rules.

```php
// ✅ GOOD: Organization-aware
public function calculateScore(Artist $artist, Organization $organization): float
{
    $metrics = MetricWeight::where('organization_id', $organization->id)
        ->where('enabled', true)
        ->get();
    // ...
}

// ❌ BAD: Organization-agnostic (won't work for multi-tenancy)
public function calculateScore(Artist $artist): float
{
    // Missing organization context!
}
```

---

## External API Service Patterns

### Spotify Service Pattern

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

### YouTube Service Pattern

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

**Key Requirements:**
- Cache external API responses aggressively
- Implement rate limiting
- Handle failures gracefully
- Never expose API keys in frontend

---

## Database Query Patterns

### Eager Loading Requirements

**Always prevent N+1 query problems:**

```php
// ✅ GOOD: Eager load relationships
$lineup = Lineup::with('artists.metrics')->find($id);

// ❌ BAD: N+1 query problem
$lineup = Lineup::find($id);
foreach ($lineup->artists as $artist) {
    $metrics = $artist->metrics; // Triggers separate query!
}
```

### Pagination

- Artist lists: 50 per page
- Always paginate large result sets

```php
$artists = Artist::query()
    ->with('metrics')
    ->paginate(50);
```

---

## Form Request Pattern

### Validation Rules

Always create Form Request classes for validation rather than inline validation in controllers.

**Check sibling Form Requests** to determine if this project uses array or string-based validation rules, then follow that convention.

### Example: UpdateScoringWeightsRequest

```php
class UpdateScoringWeightsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'weights' => ['required', 'array'],
            'weights.*.metric_name' => ['required', 'string'],
            'weights.*.weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'weights.*.enabled' => ['boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation: weights must sum to 1.00
            $enabledWeights = collect($this->weights)
                ->where('enabled', true)
                ->sum('weight');

            if (abs($enabledWeights - 1.0) > 0.01) {
                $validator->errors()->add('weights', 'Enabled metric weights must sum to 1.00');
            }
        });
    }
}
```

---

## Policy Pattern

### Organization-Level Authorization

```php
class OrganizationPolicy
{
    public function update(User $user, Organization $organization): bool
    {
        return $user->organizations()
            ->wherePivot('role', ['owner', 'admin'])
            ->where('organizations.id', $organization->id)
            ->exists();
    }

    public function manageWeights(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}
```

### Lineup-Level Authorization

```php
class LineupPolicy
{
    public function update(User $user, Lineup $lineup): bool
    {
        return $user->organizations()
            ->where('organizations.id', $lineup->organization_id)
            ->wherePivot('role', ['owner', 'admin', 'member'])
            ->exists();
    }
}
```

---

## Job Pattern

### Background Processing for External APIs

```php
class RefreshArtistMetricsJob implements ShouldQueue
{
    public function __construct(
        public Artist $artist,
        public Organization $organization
    ) {}

    public function handle(
        SpotifyService $spotify,
        YouTubeService $youtube,
        ArtistScoringService $scoringService
    ): void {
        // Fetch fresh data from APIs
        // Update artist metrics
        // Recalculate score
    }
}
```

---

## Code Organization Reference

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

---

## Required Service Classes

All service classes MUST be located in `app/Services/`:

- `SpotifyService` - Spotify API integration
- `YouTubeService` - YouTube API integration
- `ArtistSearchService` - Artist discovery/creation
- `ArtistScoringService` - Score calculation (organization-aware)
- `TierCalculationService` - Lineup tier assignments
- `OrganizationService` - Organization creation, member management

---

## Model Relationship Patterns

### Organization → Users (Many-to-Many)

```php
// Organization.php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('role')
        ->withTimestamps();
}

// User.php
public function organizations(): BelongsToMany
{
    return $this->belongsToMany(Organization::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

### Organization → MetricWeights (One-to-Many)

```php
// Organization.php
public function metricWeights(): HasMany
{
    return $this->hasMany(MetricWeight::class);
}

// MetricWeight.php
public function organization(): BelongsTo
{
    return $this->belongsTo(Organization::class);
}
```

### Lineup → Artists (Many-to-Many with Pivot Data)

```php
// Lineup.php
public function artists(): BelongsToMany
{
    return $this->belongsToMany(Artist::class, 'lineup_artists')
        ->withPivot('tier', 'suggested_tier', 'tier_override')
        ->withTimestamps();
}

// Use LineupArtist pivot model for complex logic
```

---

## Configuration Pattern

### Default Metric Presets

Store in `config/artist-tree.php`:

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

### Normalization Max Values

```php
// config/artist-tree.php
return [
    'normalization_max' => [
        'spotify_monthly_listeners' => 100_000_000,
        'spotify_popularity' => 100,
        'youtube_subscribers' => 50_000_000,
    ],
];
```

**Access via:** `config('artist-tree.normalization_max.spotify_monthly_listeners')`

---

For comprehensive Laravel framework guidelines, see `.claude/guidelines/laravel-boost.md`.

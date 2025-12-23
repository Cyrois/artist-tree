# Testing Patterns with Pest

## Artist-Tree Specific Testing Requirements

This document contains testing patterns specific to Artist-Tree. Always consult `.claude/guidelines/laravel-boost.md` for comprehensive Pest framework guidelines.

---

## Required Unit Tests

### ArtistScoringService

**MUST test edge cases:**
- Mega-stars (>10M followers)
- Unsigned artists (<1000 followers)
- Missing data scenarios (no YouTube channel → treated as 0)
- Zero values vs NULL values
- Different organization weights produce different scores
- Disabled metrics excluded from calculation entirely
- Artist with all NULL values returns score of 0

**Example Test:**

```php
it('calculates artist score correctly', function () {
    $organization = Organization::factory()->create();
    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 1_000_000,
        'spotify_popularity' => 80,
        'youtube_subscribers' => 500_000,
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    expect($score)->toBeGreaterThan(0)
        ->toBeLessThanOrEqual(100);
});
```

**Edge Case Tests:**

```php
it('handles mega-stars with 10M+ followers', function () {
    $organization = Organization::factory()->create();
    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 15_000_000,
        'spotify_popularity' => 95,
        'youtube_subscribers' => 8_000_000,
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    expect($score)->toBeGreaterThan(80)
        ->toBeLessThanOrEqual(100);
});

it('handles unsigned artists with <1000 followers', function () {
    $organization = Organization::factory()->create();
    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 250,
        'spotify_popularity' => 15,
        'youtube_subscribers' => 100,
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    expect($score)->toBeGreaterThan(0)
        ->toBeLessThan(30);
});

it('treats missing data as zero and penalizes score', function () {
    $organization = Organization::factory()->create();
    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 1_000_000,
        'spotify_popularity' => 75,
        'youtube_subscribers' => null, // Missing YouTube data
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    // Score should be lower due to zero YouTube contribution
    expect($score)->toBeLessThan(70);
});

it('calculates different scores for different organization weights', function () {
    $org1 = Organization::factory()->create();
    $org1->metricWeights()->create([
        'metric_name' => 'spotify_monthly_listeners',
        'weight' => 0.70, // Heavy Spotify weight
        'enabled' => true,
    ]);

    $org2 = Organization::factory()->create();
    $org2->metricWeights()->create([
        'metric_name' => 'youtube_subscribers',
        'weight' => 0.70, // Heavy YouTube weight
        'enabled' => true,
    ]);

    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 5_000_000,
        'youtube_subscribers' => 100_000, // Much lower YouTube
    ]);

    $service = new ArtistScoringService();
    $score1 = $service->calculateScore($artist, $org1);
    $score2 = $service->calculateScore($artist, $org2);

    expect($score1)->toBeGreaterThan($score2);
});

it('excludes disabled metrics from calculation', function () {
    $organization = Organization::factory()->create();
    $organization->metricWeights()->create([
        'metric_name' => 'youtube_subscribers',
        'weight' => 0.30,
        'enabled' => false, // Disabled
    ]);

    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => 1_000_000,
        'youtube_subscribers' => 5_000_000, // High YouTube, but disabled
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    // Score should only use enabled metrics
    expect($score)->toBeGreaterThan(0);
});

it('returns zero for artist with all null values', function () {
    $organization = Organization::factory()->create();
    $artist = Artist::factory()->create([
        'spotify_monthly_listeners' => null,
        'spotify_popularity' => null,
        'youtube_subscribers' => null,
    ]);

    $service = new ArtistScoringService();
    $score = $service->calculateScore($artist, $organization);

    expect($score)->toBe(0.0);
});
```

### TierCalculationService

```php
it('assigns correct tier percentages', function () {
    $lineup = Lineup::factory()->create();
    $artists = Artist::factory()->count(100)->create();

    $service = new TierCalculationService();
    $service->recalculateTiers($lineup, $artists);

    $lineup->refresh();

    $headliners = $lineup->artists()->wherePivot('tier', 'headliner')->count();
    $subHeadliners = $lineup->artists()->wherePivot('tier', 'sub_headliner')->count();
    $midTier = $lineup->artists()->wherePivot('tier', 'mid_tier')->count();
    $undercard = $lineup->artists()->wherePivot('tier', 'undercard')->count();

    // Top 10% = headliner
    expect($headliners)->toBe(10);
    // Next 20% = sub_headliner
    expect($subHeadliners)->toBe(20);
    // Next 40% = mid_tier
    expect($midTier)->toBe(40);
    // Bottom 30% = undercard
    expect($undercard)->toBe(30);
});

it('preserves manual tier overrides during recalculation', function () {
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->create();

    $lineup->artists()->attach($artist, [
        'tier' => 'headliner',
        'suggested_tier' => 'undercard',
        'tier_override' => true, // Manual placement
    ]);

    $service = new TierCalculationService();
    $service->recalculateTiers($lineup);

    $lineup->refresh();

    // Manual tier should stay as headliner
    expect($lineup->artists()->first()->pivot->tier)->toBe('headliner');
    expect($lineup->artists()->first()->pivot->tier_override)->toBeTrue();
});
```

---

## Required Feature Tests

### API Endpoints

**REQUIRED for:**
- All API endpoints (CRUD operations)
- Artist search functionality
- Lineup creation and artist addition
- Tier recalculation

**Example:**

```php
it('adds artist to lineup and recalculates tiers', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->for($user)->create();
    $artist = Artist::factory()->withMetrics()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/lineups/{$lineup->id}/artists", [
            'artist_id' => $artist->id
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.artists.0.pivot.tier', 'headliner');
});
```

---

## Mock External APIs

**NEVER hit real Spotify/YouTube APIs in tests:**

```php
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'api.spotify.com/*' => Http::response([
            'access_token' => 'fake-token-12345',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
        'https://api.spotify.com/v1/search*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'spotify-artist-123',
                        'name' => 'Test Artist',
                        'followers' => ['total' => 1_000_000],
                        'popularity' => 85,
                        'genres' => ['indie', 'rock'],
                        'images' => [['url' => 'https://example.com/image.jpg']],
                    ],
                ],
            ],
        ]),
        'www.googleapis.com/youtube/*' => Http::response([
            'items' => [
                [
                    'id' => ['channelId' => 'youtube-channel-123'],
                    'snippet' => ['title' => 'Test Artist'],
                    'statistics' => [
                        'subscriberCount' => '500000',
                        'videoCount' => '120',
                    ],
                ],
            ],
        ]),
    ]);
});

it('fetches artist data from Spotify API', function () {
    $service = app(SpotifyService::class);
    $result = $service->searchArtist('Test Artist');

    expect($result)->toHaveKey('id')
        ->and($result['name'])->toBe('Test Artist')
        ->and($result['followers'])->toBe(1_000_000);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.spotify.com/v1/search');
    });
});
```

---

## Test Factories

### Required Factories

```php
Artist::factory()->create()
Artist::factory()->withMetrics()->create()
Lineup::factory()->withArtists(10)->create()
```

### Factory Patterns

**OrganizationFactory:**

```php
// database/factories/OrganizationFactory.php
public function definition(): array
{
    return [
        'name' => fake()->company() . ' Festival',
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

public function withBalancedWeights(): static
{
    return $this->afterCreating(function (Organization $organization) {
        $presets = config('artist-tree.metric_presets.balanced');
        foreach ($presets as $metric => $weight) {
            $organization->metricWeights()->create([
                'metric_name' => $metric,
                'weight' => $weight,
                'enabled' => true,
            ]);
        }
    });
}
```

**ArtistFactory:**

```php
// database/factories/ArtistFactory.php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'spotify_id' => 'spotify-' . fake()->uuid(),
        'spotify_monthly_listeners' => fake()->numberBetween(1000, 10_000_000),
        'spotify_popularity' => fake()->numberBetween(20, 100),
        'youtube_subscribers' => fake()->numberBetween(500, 5_000_000),
        'youtube_channel_id' => 'youtube-' . fake()->uuid(),
        'genres' => fake()->randomElements(['rock', 'indie', 'electronic', 'pop'], 2),
        'image_url' => fake()->imageUrl(),
    ];
}

public function withMetrics(): static
{
    return $this->afterCreating(function (Artist $artist) {
        ArtistMetric::factory()->create([
            'artist_id' => $artist->id,
            'spotify_monthly_listeners' => $artist->spotify_monthly_listeners,
            'spotify_popularity' => $artist->spotify_popularity,
            'youtube_subscribers' => $artist->youtube_subscribers,
            'recorded_at' => now(),
        ]);
    });
}
```

**LineupFactory:**

```php
// database/factories/LineupFactory.php
public function definition(): array
{
    return [
        'name' => fake()->words(3, true) . ' Lineup',
        'organization_id' => Organization::factory(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

public function withArtists(int $count = 10): static
{
    return $this->afterCreating(function (Lineup $lineup) use ($count) {
        $artists = Artist::factory()->count($count)->create();

        $service = app(TierCalculationService::class);
        $service->addArtistsToLineup($lineup, $artists);
    });
}
```

---

## Browser Testing with Pest v4

Use for E2E scenarios:

```php
// tests/Browser/LineupBuilderTest.php
it('allows dragging artists between tiers', function () {
    $user = User::factory()->create();
    $lineup = Lineup::factory()->withArtists(10)->create();

    $this->actingAs($user);

    $page = visit("/lineups/{$lineup->id}");

    $page->assertSee('Headliner')
        ->assertSee('Sub-Headliner')
        ->drag('[data-artist-id="1"]', '[data-tier="sub_headliner"]')
        ->assertSee('⚠️ Manual'); // Shows manual override indicator

    // Verify database updated
    expect($lineup->fresh()->artists()->find(1)->pivot->tier_override)->toBeTrue();
});

it('displays artist search autocomplete', function () {
    $user = User::factory()->create();

    $page = visit('/artists/search')
        ->type('[data-search-input]', 'Arctic')
        ->wait(500) // Wait for debounce
        ->assertSee('Arctic Monkeys');
});
```

---

## Performance Testing

### Response Time Assertions

```php
it('artist search responds within 500ms', function () {
    $start = microtime(true);

    $response = $this->getJson('/api/artists/search?q=test');

    $duration = (microtime(true) - $start) * 1000;

    $response->assertSuccessful();
    expect($duration)->toBeLessThan(500);
});

it('lineup tier recalculation completes within 1 second', function () {
    $lineup = Lineup::factory()->withArtists(100)->create();

    $start = microtime(true);

    $response = $this->postJson("/api/lineups/{$lineup->id}/recalculate-tiers");

    $duration = (microtime(true) - $start) * 1000;

    $response->assertSuccessful();
    expect($duration)->toBeLessThan(1000);
});
```

---

## Testing Checklist

Before finalizing code:

- [ ] Unit tests for ArtistScoringService with edge cases
- [ ] Unit tests for TierCalculationService
- [ ] Feature tests for all API endpoints
- [ ] External APIs mocked (Spotify, YouTube)
- [ ] Factories created/updated for new models
- [ ] Performance targets met (<500ms search, <1s tier calc)
- [ ] Browser tests for critical user flows (if applicable)
- [ ] All tests pass: `php artisan test`

---

For comprehensive Pest framework guidelines and browser testing, see `.claude/guidelines/laravel-boost.md`.

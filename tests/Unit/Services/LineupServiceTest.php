<?php

use App\Enums\ArtistTier;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Models\Lineup;
use App\Services\LineupService;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->lineupService = new LineupService();
});

it('includes stacking info in lineup payload', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    $artist2 = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    
    $stackId = (string) Str::uuid();
    
    $lineup->artists()->attach($artist1->id, [
        'tier' => ArtistTier::Headliner->value,
        'stack_id' => $stackId,
        'is_stack_primary' => true,
    ]);
    
    $lineup->artists()->attach($artist2->id, [
        'tier' => ArtistTier::Headliner->value,
        'stack_id' => $stackId,
        'is_stack_primary' => false,
    ]);

    $payload = $this->lineupService->getLineupPayload($lineup);

    $headliners = $payload['artists'][ArtistTier::Headliner->value];
    
    expect($headliners)->toHaveCount(2);
    
    $a1 = collect($headliners)->firstWhere('id', $artist1->id);
    $a2 = collect($headliners)->firstWhere('id', $artist2->id);
    
    expect($a1['stack_id'])->toBe($stackId)
        ->and($a1['is_stack_primary'])->toBeTrue();
        
    expect($a2['stack_id'])->toBe($stackId)
        ->and($a2['is_stack_primary'])->toBeFalse();
});

it('handles lineups without stacking info', function () {
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->has(ArtistMetric::factory(), 'metrics')->create();
    
    $lineup->artists()->attach($artist->id, [
        'tier' => ArtistTier::Headliner->value,
    ]);

    $payload = $this->lineupService->getLineupPayload($lineup);

    $headliners = $payload['artists'][ArtistTier::Headliner->value];
    
    expect($headliners)->toHaveCount(1);
    
    $a = $headliners[0];
    
    expect($a['stack_id'])->toBeNull()
        ->and($a['is_stack_primary'])->toBeFalse();
});

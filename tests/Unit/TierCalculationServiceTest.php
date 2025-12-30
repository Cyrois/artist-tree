<?php

namespace Tests\Unit;

use App\Models\Artist;
use App\Models\Lineup;
use App\Services\ArtistScoringService;
use App\Services\TierCalculationService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class TierCalculationServiceTest extends TestCase
{
    public function test_determine_tier_returns_headliner_for_top_10_percent()
    {
        // Setup mock scoring service
        $scoringService = Mockery::mock(ArtistScoringService::class);
        $service = new TierCalculationService($scoringService);

        // Create a lineup
        $lineup = Lineup::factory()->create();
        
        // We want 10 items total. Top 1 (10%) should be headliner.
        // Create 9 existing artists with low scores
        $existingArtists = Artist::factory()->count(9)->create();
        $lineup->artists()->attach($existingArtists);

        // New artist with high score
        $newArtist = Artist::factory()->create();

        // Mock scores: existing are 10-90, new is 100
        foreach ($existingArtists as $index => $artist) {
            $scoringService->shouldReceive('calculateScore')
                ->with(Mockery::on(fn($a) => $a->id === $artist->id))
                ->andReturn(10 + $index);
        }
        $scoringService->shouldReceive('calculateScore')
            ->with(Mockery::on(fn($a) => $a->id === $newArtist->id))
            ->andReturn(100); // Highest score

        $tier = $service->determineTier($newArtist, $lineup);

        // 1st out of 10 = 10th percentile => <= 10% => headliner
        $this->assertEquals('headliner', $tier);
    }

    public function test_determine_tier_returns_sub_headliner_for_next_20_percent()
    {
        $scoringService = Mockery::mock(ArtistScoringService::class);
        $service = new TierCalculationService($scoringService);
        $lineup = Lineup::factory()->create();
        
        // 10 items total. Rank 2 (20%) or 3 (30%) should be sub_headliner.
        // Let's aim for Rank 2.
        $existingArtists = Artist::factory()->count(9)->create();
        $lineup->artists()->attach($existingArtists);
        $newArtist = Artist::factory()->create();

        // Rank 1 (Headliner): 100
        // Rank 2 (New Artist): 90
        // Rank 3..10: 80..10
        
        // We need to identify which existing artist is the headliner to give them score 100
        $headliner = $existingArtists->first();
        $others = $existingArtists->slice(1);

        $scoringService->shouldReceive('calculateScore')
            ->with(Mockery::on(fn($a) => $a->id === $headliner->id))
            ->andReturn(100);
            
        foreach ($others as $index => $artist) {
            $scoringService->shouldReceive('calculateScore')
                ->with(Mockery::on(fn($a) => $a->id === $artist->id))
                ->andReturn(10 + $index);
        }
        
        $scoringService->shouldReceive('calculateScore')
            ->with(Mockery::on(fn($a) => $a->id === $newArtist->id))
            ->andReturn(90); // 2nd highest

        $tier = $service->determineTier($newArtist, $lineup);

        // 2nd out of 10 = 20th percentile => <= 30% => sub_headliner
        $this->assertEquals('sub_headliner', $tier);
    }

    public function test_determine_tier_returns_undercard_for_bottom_30_percent()
    {
        $scoringService = Mockery::mock(ArtistScoringService::class);
        $service = new TierCalculationService($scoringService);
        $lineup = Lineup::factory()->create();
        
        // 10 items total. Rank 8, 9, 10 should be undercard.
        $existingArtists = Artist::factory()->count(9)->create();
        $lineup->artists()->attach($existingArtists);
        $newArtist = Artist::factory()->create();

        // Make new artist have lowest score
        foreach ($existingArtists as $index => $artist) {
            $scoringService->shouldReceive('calculateScore')
                ->with(Mockery::on(fn($a) => $a->id === $artist->id))
                ->andReturn(50 + $index);
        }
        $scoringService->shouldReceive('calculateScore')
            ->with(Mockery::on(fn($a) => $a->id === $newArtist->id))
            ->andReturn(10); // Lowest

        $tier = $service->determineTier($newArtist, $lineup);

        // 10th out of 10 = 100th percentile => undercard
        $this->assertEquals('undercard', $tier);
    }
}

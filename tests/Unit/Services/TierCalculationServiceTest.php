<?php

namespace Tests\Unit\Services;

use App\Enums\ArtistTier;
use App\Models\Artist;
use App\Models\Lineup;
use App\Models\LineupArtist;
use App\Services\ArtistScoringService;
use App\Services\TierCalculationService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class TierCalculationServiceTest extends TestCase
{
    private TierCalculationService $tierService;
    private $scoringServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoringServiceMock = Mockery::mock(ArtistScoringService::class);
        $this->tierService = new TierCalculationService($this->scoringServiceMock);
    }

    public function test_suggest_tier_returns_null_when_lineup_is_empty()
    {
        // Use a real instance but partial mock to intercept loadMissing
        $lineup = Mockery::mock(Lineup::class)->makePartial();
        $lineup->shouldReceive('loadMissing')->once();
        
        // Use setRelation to simulate loaded relationship
        $lineup->setRelation('artists', new Collection([]));

        $result = $this->tierService->suggestTier($lineup, 80);

        $this->assertNull($result);
    }

    public function test_suggest_tier_returns_closest_average_tier()
    {
        $lineup = Mockery::mock(Lineup::class)->makePartial();
        $lineup->shouldReceive('loadMissing')->times(2);

        // Mock Artists
        $headliner1 = new Artist();
        $headliner1->setRelation('pivot', new LineupArtist(['tier' => ArtistTier::Headliner->value]));
        $this->scoringServiceMock->shouldReceive('calculateScore')->with($headliner1)->andReturn(90);

        $sub1 = new Artist();
        $sub1->setRelation('pivot', new LineupArtist(['tier' => ArtistTier::SubHeadliner->value]));
        $this->scoringServiceMock->shouldReceive('calculateScore')->with($sub1)->andReturn(70);

        $lineup->setRelation('artists', new Collection([$headliner1, $sub1]));

        // Test case 1: 85 is closer to 90 (diff 5) than 70 (diff 15)
        $result = $this->tierService->suggestTier($lineup, 85);
        $this->assertEquals(ArtistTier::Headliner, $result);

        // Test case 2: 75 is closer to 70 (diff 5) than 90 (diff 15)
        $result2 = $this->tierService->suggestTier($lineup, 75);
        $this->assertEquals(ArtistTier::SubHeadliner, $result2);
    }
}

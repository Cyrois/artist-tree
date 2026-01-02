<?php

namespace App\Services;

use App\Enums\ArtistTier;
use App\Http\Resources\ArtistResource;
use App\Models\Lineup;
use App\Services\ArtistScoringService;

class LineupService
{
    /**
     * Format the lineup data for frontend consumption.
     */
    public function getLineupPayload(Lineup $lineup): array
    {
        $lineup->load(['artists.metrics']);
        
        // Group artists by tier
        $artistsByTier = array_fill_keys(ArtistTier::values(), []);

        foreach ($lineup->artists as $artist) {
            $tier = $artist->pivot->tier;
            if (array_key_exists($tier, $artistsByTier)) {
                $artistData = (new ArtistResource($artist))->resolve();
                $artistData['lineup_tier'] = $tier;
                $artistData['stack_id'] = $artist->pivot->stack_id;
                $artistData['is_stack_primary'] = (bool) $artist->pivot->is_stack_primary;
                
                $artistsByTier[$tier][] = $artistData;
            }
        }
        
        $artistCount = $lineup->artists->count();
        $scoringService = app(ArtistScoringService::class);
        $totalScore = $lineup->artists->sum(fn ($artist) => $scoringService->calculateScore($artist));
        
        $avgScore = $artistCount > 0 ? round($totalScore / $artistCount) : 0;

        return [
            'id' => $lineup->id,
            'name' => $lineup->name,
            'description' => $lineup->description,
            'updated_at' => $lineup->updated_at,
            'updated_at_human' => $lineup->updated_at->diffForHumans(),
            'artists' => $artistsByTier,
            'artistStatuses' => [], 
            'stats' => [
                'artist_count' => $artistCount,
                'avg_score' => $avgScore,
            ]
        ];
    }
}

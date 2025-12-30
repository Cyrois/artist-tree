<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Lineup;
use Illuminate\Support\Collection;

class TierCalculationService
{
    protected ArtistScoringService $scoringService;

    public function __construct(ArtistScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    /**
     * Determine the appropriate tier for an artist being added to a lineup.
     */
    public function determineTier(Artist $newArtist, Lineup $lineup): string
    {
        // Get all existing artists with their metrics
        $artists = $lineup->artists()->with('metrics')->get();

        // Eager load metrics for the new artist to prevent N+1 queries
        $newArtist->loadMissing('metrics');
        
        // Add the new artist to the collection for calculation
        $artists->push($newArtist);

        // Calculate scores for all artists
        $scoredArtists = $artists->map(function ($artist) {
            return [
                'id' => $artist->id,
                'score' => $this->scoringService->calculateScore($artist),
            ];
        })->sortByDesc('score')->values();

        // Find the index of the new artist
        $index = $scoredArtists->search(fn ($item) => $item['id'] === $newArtist->id);
        $totalArtists = $scoredArtists->count();
        $percentile = ($index + 1) / $totalArtists; // Rank percentile (lower is better rank)

        // Top 10% = headliner
        if ($percentile <= 0.10) {
            return 'headliner';
        }

        // Next 20% (10% to 30%) = sub_headliner
        if ($percentile <= 0.30) {
            return 'sub_headliner';
        }

        // Next 40% (30% to 70%) = mid_tier
        if ($percentile <= 0.70) {
            return 'mid_tier';
        }

        // Bottom 30% = undercard
        return 'undercard';
    }
}

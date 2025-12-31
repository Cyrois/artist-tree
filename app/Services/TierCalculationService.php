<?php

namespace App\Services;

use App\Models\Lineup;

class TierCalculationService
{
    private array $tiers = ['headliner', 'sub_headliner', 'mid_tier', 'undercard'];

    public function __construct(
        private ArtistScoringService $scoringService
    ) {}

    public function suggestTier(Lineup $lineup, int $artistScore): ?string
    {
        $lineup->loadMissing(['artists.metrics']);

        $tierScores = [];

        foreach ($this->tiers as $tier) {
            $artistsInTier = $lineup->artists->filter(function ($artist) use ($tier) {
                return $artist->pivot->tier === $tier;
            });

            if ($artistsInTier->isEmpty()) {
                continue;
            }

            $totalScore = $artistsInTier->sum(function ($artist) {
                return $this->scoringService->calculateScore($artist);
            });

            $avgScore = $totalScore / $artistsInTier->count();
            $tierScores[$tier] = $avgScore;
        }

        if (empty($tierScores)) {
            return null;
        }

        $bestTier = null;
        $minDiff = INF;

        foreach ($tierScores as $tier => $avgScore) {
            $diff = abs($artistScore - $avgScore);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $bestTier = $tier;
            }
        }

        return $bestTier;
    }
}

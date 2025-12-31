<?php

namespace App\Services;

use App\Enums\ArtistTier;
use App\Models\Lineup;

class TierCalculationService
{
    public function __construct(
        private ArtistScoringService $scoringService
    ) {}

    public function suggestTier(Lineup $lineup, int $artistScore): ?ArtistTier
    {
        $lineup->loadMissing(['artists.metrics']);

        $tierScores = [];

        foreach (ArtistTier::cases() as $tier) {
            $artistsInTier = $lineup->artists->filter(function ($artist) use ($tier) {
                return $artist->pivot->tier === $tier->value;
            });

            if ($artistsInTier->isEmpty()) {
                continue;
            }

            $totalScore = $artistsInTier->sum(function ($artist) {
                return $this->scoringService->calculateScore($artist);
            });

            $avgScore = $totalScore / $artistsInTier->count();
            $tierScores[$tier->value] = $avgScore;
        }

        if (empty($tierScores)) {
            return null;
        }

        $bestTier = null;
        $minDiff = INF;

        foreach ($tierScores as $tierValue => $avgScore) {
            $diff = abs($artistScore - $avgScore);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $bestTier = ArtistTier::from($tierValue);
            }
        }

        return $bestTier;
    }
}

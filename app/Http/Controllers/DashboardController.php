<?php

namespace App\Http\Controllers;

use App\Http\Resources\LineupResource;
use App\Services\ArtistScoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ArtistScoringService $scoringService)
    {
        $lineups = $request->user()
            ->lineups()
            ->withCount('artists')
            ->with(['artists' => function ($query) {
                // Eager load up to 5 artists per lineup for preview
                $query->orderByPivot('tier')->limit(5);
            }])
            ->latest('updated_at')
            ->take(3)
            ->get();

        // Calculate average score for each lineup
        foreach ($lineups as $lineup) {
            if ($lineup->artists_count === 0) {
                $lineup->avg_score = 0;

                continue;
            }

            // Fetch all artists with metrics to calculate true average
            // This incurs N extra queries (where N=3), which is acceptable for dashboard
            $allArtists = $lineup->artists()->with('metrics')->get();
            $totalScore = $allArtists->sum(fn ($artist) => $scoringService->calculateScore($artist));
            $lineup->avg_score = round($totalScore / $allArtists->count());
        }

        return Inertia::render('Dashboard', [
            'lineups' => LineupResource::collection($lineups),
        ]);
    }
}

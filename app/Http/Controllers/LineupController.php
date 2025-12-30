<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLineupRequest;
use App\Http\Requests\UpdateLineupRequest;
use App\Http\Requests\AddArtistToLineupRequest;
use App\Models\Artist;
use App\Models\Lineup;
use App\Http\Resources\ArtistResource;
use App\Services\ArtistScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LineupController extends Controller
{
    public function index()
    {
        $lineups = Lineup::with(['artists' => function($query) {
            $query->limit(10);
        }])
        ->withCount('artists')
        ->orderByDesc('updated_at')
        ->get();

        return Inertia::render('Lineups/Index', [
            'lineups' => \App\Http\Resources\LineupResource::collection($lineups)
        ]);
    }

    public function store(StoreLineupRequest $request)
    {
        $lineup = Lineup::create($request->validated());

        // Associate the user with the lineup as owner
        $lineup->users()->attach(auth()->id(), ['role' => 'owner']);

        return redirect()->route('lineups.show', $lineup->id);
    }

    public function update(UpdateLineupRequest $request, Lineup $lineup)
    {
        Gate::authorize('update', $lineup);

        $lineup->update($request->validated());

        return redirect()->back();
    }

    public function destroy(Lineup $lineup)
    {
        Gate::authorize('delete', $lineup);

        $lineup->delete();

        return redirect()->route('lineups.index')->with('success', 'Lineup deleted successfully.');
    }

    public function addArtist(Lineup $lineup, AddArtistToLineupRequest $request)
    {
        // Check authorization
        // $this->authorize('update', $lineup); // Policy not implemented yet
        $validated = $request->validated();
        
        $artist = Artist::findOrFail($validated['artist_id']);
        $tier = $validated['tier'];

        // Attach artist if not already in lineup
        if (!$lineup->artists()->where('artist_id', $artist->id)->exists()) {
            $lineup->artists()->attach($artist->id, [
                'tier' => $tier,
            ]);
        }

        return redirect()->back();
    }

        if ($lineup->artists()->detach($artist->id)) {
            return redirect()->back()->with('success', 'Artist removed successfully.');
        }

        return redirect()->back();

    public function show($id)
    {
        $lineup = Lineup::with(['artists.metrics'])->findOrFail($id);
        
        // Group artists by tier
        $artistsByTier = [
            'headliner' => [],
            'sub_headliner' => [],
            'mid_tier' => [],
            'undercard' => [],
        ];

        foreach ($lineup->artists as $artist) {
            $tier = $artist->pivot->tier;
            if (array_key_exists($tier, $artistsByTier)) {
                // Use ArtistResource to format
                $artistData = (new ArtistResource($artist))->resolve();
                // Add pivot data
                $artistData['lineup_tier'] = $tier;
                
                $artistsByTier[$tier][] = $artistData;
            }
        }
        
        // Calculate avg score
        $artistCount = $lineup->artists->count();
        $scoringService = app(ArtistScoringService::class);
        $totalScore = $lineup->artists->sum(fn ($artist) => $scoringService->calculateScore($artist));
        
        $avgScore = $artistCount > 0 ? round($totalScore / $artistCount) : 0;

        return Inertia::render('Lineups/Show', [
            'id' => $lineup->id,
            'lineup' => [
                'id' => $lineup->id,
                'name' => $lineup->name,
                'description' => $lineup->description,
                'updatedAt' => $lineup->updated_at->diffForHumans(),
                'artists' => $artistsByTier,
                'artistStatuses' => [], // Empty as requested
                'stats' => [
                    'artistCount' => $artistCount,
                    'avgScore' => $avgScore,
                ]
            ],
        ]);
    }

    private function getMockArtists($count)
    {
        // Get random artists from DB
        $artists = Artist::inRandomOrder()->limit($count)->get();
        
        // If we don't have enough artists in DB, we'll just loop what we have
        if ($artists->isEmpty()) {
            return [];
        }

        $tiers = ['headliner', 'sub_headliner', 'mid_tier', 'undercard'];
        
        return $artists->map(function ($artist) use ($tiers) {
            return [
                'id' => $artist->id,
                'name' => $artist->name,
                'image_url' => $artist->image_url,
                'tier' => $tiers[array_rand($tiers)], // Randomly assign tier
            ];
        })->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ArtistTier;
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

use App\Services\TierCalculationService;

use App\Services\LineupService;
use App\Services\LineupStackService;

class LineupController extends Controller
{
    public function __construct(
        protected LineupService $lineupService,
        protected LineupStackService $stackService
    ) {}

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

    public function suggestTier(Request $request, Lineup $lineup, TierCalculationService $tierService, ArtistScoringService $scoringService)
    {
        $request->validate([
            'artist_id' => 'required_without:score|nullable|exists:artists,id',
            'score' => 'required_without:artist_id|numeric|min:0|max:100',
        ]);

        if ($request->has('score')) {
            $score = (int) $request->score;
        } else {
            $artist = Artist::findOrFail($request->artist_id);
            $score = $scoringService->calculateScore($artist);
        }
        
        $suggestedTier = $tierService->suggestTier($lineup, $score);

        return response()->json([
            'suggested_tier' => $suggestedTier?->value,
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

        if ($request->expectsJson()) {
            return response()->json([
                'lineup' => $this->lineupService->getLineupPayload($lineup),
                'message' => 'Artist added to lineup successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Artist added to lineup successfully.');
    }

    public function removeArtist(Lineup $lineup, Artist $artist, Request $request)
    {
        Gate::authorize('update', $lineup);

        // Clean up stacking before removing from lineup
        $this->stackService->removeArtistFromStack($lineup->id, $artist->id);

        if ($lineup->artists()->detach($artist->id)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'lineup' => $this->lineupService->getLineupPayload($lineup),
                    'message' => 'Artist removed successfully.'
                ]);
            }
            return redirect()->back()->with('success', 'Artist removed successfully.');
        }

        return redirect()->back();
    }

    public function show($id)
    {
        $lineup = Lineup::findOrFail($id);
        
        return Inertia::render('Lineups/Show', [
            'id' => $lineup->id,
            'lineup' => $this->lineupService->getLineupPayload($lineup),
        ]);
    }
}

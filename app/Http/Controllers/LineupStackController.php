<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromoteStackArtistRequest;
use App\Http\Requests\StoreLineupStackRequest;
use App\Models\Lineup;
use App\Services\LineupService;
use App\Services\LineupStackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LineupStackController extends Controller
{
    public function __construct(
        protected LineupStackService $stackService,
        protected LineupService $lineupService
    ) {}

    /**
     * Create a stack or add an artist to an existing stack.
     */
    public function store(Lineup $lineup, StoreLineupStackRequest $request): JsonResponse
    {
        Gate::authorize('update', $lineup);

        $artistId = $request->validated('artist_id');

        if (! $this->stackService->isArtistInLineup($lineup->id, $artistId)) {
            return response()->json(['error' => 'Artist not in lineup.'], 422);
        }

        $this->stackService->addToStack(
            $lineup->id,
            $artistId,
            $request->validated('stack_id')
        );

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Stack updated.',
        ]);
    }

    /**
     * Promote an artist to primary within a stack.
     */
    public function promote(Lineup $lineup, string $stack_id, PromoteStackArtistRequest $request): JsonResponse
    {
        Gate::authorize('update', $lineup);

        $this->stackService->promoteArtist(
            $lineup->id,
            $stack_id,
            $request->validated('artist_id')
        );

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Artist promoted to primary.',
        ]);
    }

    /**
     * Remove an artist from a stack.
     */
    public function removeArtist(Lineup $lineup, \App\Models\Artist $artist): JsonResponse
    {
        Gate::authorize('update', $lineup);

        $this->stackService->removeArtistFromStack($lineup->id, $artist->id);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Artist removed from stack.',
        ]);
    }

    /**
     * Dissolve a stack entirely.
     */
    public function dissolve(Lineup $lineup, string $stack_id): JsonResponse
    {
        Gate::authorize('update', $lineup);

        $this->stackService->dissolveStack($lineup->id, $stack_id);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Stack dissolved.',
        ]);
    }
}

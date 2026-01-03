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
    public function store(int $lineupId, StoreLineupStackRequest $request): JsonResponse
    {
        $lineup = Lineup::findOrFail($lineupId);
        Gate::authorize('update', $lineup);

        $artistId = $request->validated('artist_id');

        if (! $this->stackService->isArtistInLineup($lineupId, $artistId)) {
            return response()->json(['error' => 'Artist not in lineup.'], 422);
        }

        $this->stackService->addToStack(
            $lineupId,
            $artistId,
            $request->validated('stack_id')
        );

        $lineup = Lineup::findOrFail($lineupId);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Stack updated.',
        ]);
    }

    /**
     * Promote an artist to primary within a stack.
     */
    public function promote(int $lineupId, string $stack_id, PromoteStackArtistRequest $request): JsonResponse
    {
        $lineup = Lineup::findOrFail($lineupId);
        Gate::authorize('update', $lineup);

        $this->stackService->promoteArtist(
            $lineupId,
            $stack_id,
            $request->validated('artist_id')
        );

        $lineup = Lineup::findOrFail($lineupId);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Artist promoted to primary.',
        ]);
    }

    /**
     * Remove an artist from a stack.
     */
    public function removeArtist(int $lineupId, int $artist): JsonResponse
    {
        $lineup = Lineup::findOrFail($lineupId);
        Gate::authorize('update', $lineup);

        $this->stackService->removeArtistFromStack($lineupId, $artist);

        $lineup = Lineup::findOrFail($lineupId);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Artist removed from stack.',
        ]);
    }

    /**
     * Dissolve a stack entirely.
     */
    public function dissolve(int $lineupId, string $stack_id): JsonResponse
    {
        $lineup = Lineup::findOrFail($lineupId);
        Gate::authorize('update', $lineup);

        $this->stackService->dissolveStack($lineupId, $stack_id);

        $lineup = Lineup::findOrFail($lineupId);

        return response()->json([
            'lineup' => $this->lineupService->getLineupPayload($lineup),
            'message' => 'Stack dissolved.',
        ]);
    }
}

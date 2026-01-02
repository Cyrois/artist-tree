<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromoteStackArtistRequest;
use App\Http\Requests\StoreLineupStackRequest;
use App\Models\Artist;
use App\Models\Lineup;
use App\Services\LineupStackService;
use Illuminate\Http\RedirectResponse;

class LineupStackController extends Controller
{
    public function __construct(
        protected LineupStackService $stackService
    ) {}

    /**
     * Create a stack or add an artist to an existing stack.
     */
    public function store(int $lineup, StoreLineupStackRequest $request): RedirectResponse
    {
        $artistId = $request->validated('artist_id');

        if (!$this->stackService->isArtistInLineup($lineup, $artistId)) {
            return redirect()->back()->with('error', 'Artist not in lineup.');
        }

        $this->stackService->addToStack(
            $lineup,
            $artistId,
            $request->validated('stack_id')
        );

        return redirect()->back()->with('success', 'Stack updated.');
    }

    /**
     * Promote an artist to primary within a stack.
     */
    public function promote(int $lineup, string $stack_id, PromoteStackArtistRequest $request): RedirectResponse
    {
        $this->stackService->promoteArtist(
            $lineup,
            $stack_id,
            $request->validated('artist_id')
        );

        return redirect()->back()->with('success', 'Artist promoted to primary.');
    }

    /**
     * Remove an artist from a stack.
     */
    public function removeArtist(int $lineup, int $artist, LineupStackService $stackService): RedirectResponse
    {
        $stackService->removeArtistFromStack($lineup, $artist);

        return redirect()->back()->with('success', 'Artist removed from stack.');
    }

    /**
     * Dissolve a stack entirely.
     */
    public function dissolve(int $lineup, string $stack_id): RedirectResponse
    {
        $this->stackService->dissolveStack($lineup, $stack_id);

        return redirect()->back()->with('success', 'Stack dissolved.');
    }
}

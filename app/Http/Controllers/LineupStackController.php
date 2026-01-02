<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Models\Lineup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LineupStackController extends Controller
{
    public function store(Lineup $lineup, Request $request)
    {
        $validated = $request->validate([
            'artist_id' => 'required|exists:artists,id',
            'stack_id' => 'nullable|string', // If present, adding to existing stack
        ]);

        $artistId = $validated['artist_id'];
        $stackId = $validated['stack_id'] ?? (string) Str::uuid();

        // Ensure artist is in the lineup
        $exists = DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('artist_id', $artistId)
            ->exists();
        
        if (!$exists) {
            return redirect()->back()->with('error', 'Artist not in lineup.');
        }

        // If it's a new stack, make this artist primary
        $isPrimary = !isset($validated['stack_id']);

        DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('artist_id', $artistId)
            ->update([
                'stack_id' => $stackId,
                'is_stack_primary' => $isPrimary,
            ]);

        return redirect()->back()->with('success', 'Stack updated.');
    }

    public function promote(Lineup $lineup, string $stackId, Request $request)
    {
        $validated = $request->validate([
            'artist_id' => 'required|exists:artists,id',
        ]);

        $newPrimaryId = $validated['artist_id'];

        // 1. Reset current primary in this stack
        DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('stack_id', $stackId)
            ->update(['is_stack_primary' => false]);

        // 2. Set new primary
        DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('artist_id', $newPrimaryId)
            ->update(['is_stack_primary' => true]);

        return redirect()->back()->with('success', 'Artist promoted to primary.');
    }

    public function removeArtist(Lineup $lineup, Artist $artist)
    {
        $pivot = DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('artist_id', $artist->id)
            ->first();

        if (!$pivot || !$pivot->stack_id) {
            return redirect()->back();
        }

        $stackId = $pivot->stack_id;
        $wasPrimary = $pivot->is_stack_primary;

        // Remove stack association
        DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('artist_id', $artist->id)
            ->update([
                'stack_id' => null,
                'is_stack_primary' => false,
            ]);

        // If it was primary, we need to pick a new primary if there are others left
        if ($wasPrimary) {
            $next = DB::table('lineup_artists')
                ->where('lineup_id', $lineup->id)
                ->where('stack_id', $stackId)
                ->first();
                
            if ($next) {
                DB::table('lineup_artists')
                    ->where('lineup_id', $lineup->id)
                    ->where('artist_id', $next->artist_id)
                    ->update(['is_stack_primary' => true]);
            }
        }

        return redirect()->back()->with('success', 'Artist removed from stack.');
    }

    public function dissolve(Lineup $lineup, string $stackId)
    {
        DB::table('lineup_artists')
            ->where('lineup_id', $lineup->id)
            ->where('stack_id', $stackId)
            ->update([
                'stack_id' => null,
                'is_stack_primary' => false,
            ]);

        return redirect()->back()->with('success', 'Stack dissolved.');
    }
}
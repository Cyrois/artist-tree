<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Lineup;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class LineupStackService
{
    /**
     * Add an artist to a stack or create a new stack.
     */
    public function addToStack(int $lineupId, int $artistId, ?string $stackId = null): string
    {
        $isPrimary = $stackId === null;
        $stackId = $stackId ?? (string) Str::uuid();

        DB::table('lineup_artists')
            ->where('lineup_id', $lineupId)
            ->where('artist_id', $artistId)
            ->update([
                'stack_id' => $stackId,
                'is_stack_primary' => $isPrimary,
            ]);

        return $stackId;
    }

    /**
     * Promote an artist to primary within a stack.
     */
    public function promoteArtist(int $lineupId, string $stackId, int $artistId): void
    {
        DB::transaction(function () use ($lineupId, $stackId, $artistId) {
            // 1. Reset current primary in this stack
            DB::table('lineup_artists')
                ->where('lineup_id', $lineupId)
                ->where('stack_id', $stackId)
                ->update(['is_stack_primary' => false]);

            // 2. Set new primary
            DB::table('lineup_artists')
                ->where('lineup_id', $lineupId)
                ->where('artist_id', $artistId)
                ->update(['is_stack_primary' => true]);
        });
    }

    /**
     * Remove an artist from a stack.
     */
    public function removeArtistFromStack(int $lineupId, int $artistId): void
    {
        $pivot = DB::table('lineup_artists')
            ->where('lineup_id', $lineupId)
            ->where('artist_id', $artistId)
            ->first();

        if (!$pivot || !$pivot->stack_id) {
            return;
        }

        $stackId = $pivot->stack_id;
        $wasPrimary = $pivot->is_stack_primary;

        DB::transaction(function () use ($lineupId, $artistId, $stackId, $wasPrimary) {
            // 1. Remove stack association for THIS artist
            DB::table('lineup_artists')
                ->where('lineup_id', $lineupId)
                ->where('artist_id', $artistId)
                ->update([
                    'stack_id' => null,
                    'is_stack_primary' => false,
                ]);

            // 2. Count how many artists are STILL in this stack
            $remaining = DB::table('lineup_artists')
                ->where('lineup_id', $lineupId)
                ->where('stack_id', $stackId)
                ->get();

            if ($remaining->count() <= 1) {
                // If 0 or 1 artists remain, they shouldn't be in a stack anymore
                DB::table('lineup_artists')
                    ->where('lineup_id', $lineupId)
                    ->where('stack_id', $stackId)
                    ->update([
                        'stack_id' => null,
                        'is_stack_primary' => false,
                    ]);
                return;
            }

            // 3. If it was primary and we have multiple left, pick a new primary
            if ($wasPrimary) {
                $next = $remaining->first();
                if ($next) {
                    DB::table('lineup_artists')
                        ->where('lineup_id', $lineupId)
                        ->where('artist_id', $next->artist_id)
                        ->update(['is_stack_primary' => true]);
                }
            }
        });
    }

    /**
     * Dissolve a stack entirely.
     */
    public function dissolveStack(int $lineupId, string $stackId): void
    {
        DB::table('lineup_artists')
            ->where('lineup_id', $lineupId)
            ->where('stack_id', $stackId)
            ->update([
                'stack_id' => null,
                'is_stack_primary' => false,
            ]);
    }

    /**
     * Check if an artist belongs to a lineup.
     */
    public function isArtistInLineup(int $lineupId, int $artistId): bool
    {
        return DB::table('lineup_artists')
            ->where('lineup_id', $lineupId)
            ->where('artist_id', $artistId)
            ->exists();
    }
}

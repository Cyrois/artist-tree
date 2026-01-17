<?php

use App\Jobs\UpdateLineupTimestamp;
use App\Models\Artist;
use App\Models\Lineup;
use App\Services\LineupStackService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->stackService = new LineupStackService;
});

it('can add an artist to a new stack', function () {
    Queue::fake();
    $lineup = Lineup::factory()->create();
    $artist = Artist::factory()->create();
    $lineup->artists()->attach($artist->id);

    $stackId = $this->stackService->addToStack($lineup->id, $artist->id);

    expect($stackId)->toBeString()
        ->and(Str::isUuid($stackId))->toBeTrue();

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist->id,
        'stack_id' => $stackId,
        'is_stack_primary' => true,
    ]);

    Queue::assertPushed(UpdateLineupTimestamp::class);
});

it('can add an artist to an existing stack', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    $returnedStackId = $this->stackService->addToStack($lineup->id, $artist2->id, $stackId);

    expect($returnedStackId)->toBe($stackId);

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'stack_id' => $stackId,
        'is_stack_primary' => false,
    ]);
});

it('can promote an artist in a stack', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist2->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => false]);

    $this->stackService->promoteArtist($lineup->id, $stackId, $artist2->id);

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist1->id,
        'is_stack_primary' => false,
    ]);

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'is_stack_primary' => true,
    ]);
});

it('can remove an artist from a stack and pick a new primary', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist2->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => false]);

    // Remove primary
    $this->stackService->removeArtistFromStack($lineup->id, $artist1->id);

    // Artist 1 should be out of stack
    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist1->id,
        'stack_id' => null,
        'is_stack_primary' => false,
    ]);

    // Artist 2 should be promoted to primary and THEN the stack should be dissolved because only 1 remains?
    // Wait, let's re-read LineupStackService@removeArtistFromStack
    /*
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
    */
    // If only artist 2 remains, it dissolves the stack.

    $this->assertDatabaseHas('lineup_artists', [
        'lineup_id' => $lineup->id,
        'artist_id' => $artist2->id,
        'stack_id' => null,
        'is_stack_primary' => false,
    ]);
});

it('promotes next artist when primary is removed from a stack with 3 artists', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $artist3 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id, $artist3->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('artist_id', $artist1->id)
        ->update(['stack_id' => $stackId, 'is_stack_primary' => true]);

    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->whereIn('artist_id', [$artist2->id, $artist3->id])
        ->update(['stack_id' => $stackId, 'is_stack_primary' => false]);

    // Remove primary
    $this->stackService->removeArtistFromStack($lineup->id, $artist1->id);

    // One of the remaining should be primary
    $remainingPrimary = DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('stack_id', $stackId)
        ->where('is_stack_primary', true)
        ->count();

    expect($remainingPrimary)->toBe(1);

    $inStackCount = DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('stack_id', $stackId)
        ->count();

    expect($inStackCount)->toBe(2);
});

it('can dissolve a stack', function () {
    $lineup = Lineup::factory()->create();
    $artist1 = Artist::factory()->create();
    $artist2 = Artist::factory()->create();
    $lineup->artists()->attach([$artist1->id, $artist2->id]);

    $stackId = (string) Str::uuid();
    DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->update(['stack_id' => $stackId]);

    $this->stackService->dissolveStack($lineup->id, $stackId);

    $stackCount = DB::table('lineup_artists')
        ->where('lineup_id', $lineup->id)
        ->where('stack_id', $stackId)
        ->count();

    expect($stackCount)->toBe(0);
});

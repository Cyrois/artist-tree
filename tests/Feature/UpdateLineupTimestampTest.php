<?php

use App\Jobs\UpdateLineupTimestamp;
use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('changing stack dispatches update lineup timestamp job', function () {
    Queue::fake();

    $lineup = Lineup::factory()->create();
    $lineup->users()->attach($this->user->id, ['role' => 'owner']);
    
    $artist = Artist::factory()->create();
    $lineup->artists()->attach($artist->id);

    $this->actingAs($this->user)
        ->postJson(route('api.lineups.stacks.store', $lineup->id), [
            'artist_id' => $artist->id,
        ])
        ->assertSuccessful();

    Queue::assertPushed(UpdateLineupTimestamp::class, function ($job) use ($lineup) {
        return $job->lineupId === $lineup->id && $job->delay !== null;
    });
});

test('job updates lineup timestamp', function () {
    $lineup = Lineup::factory()->create([
        'updated_at' => now()->subHours(1),
    ]);
    $originalTimestamp = $lineup->updated_at;

    $job = new UpdateLineupTimestamp($lineup->id);
    $job->handle();

    $lineup->refresh();
    expect($lineup->updated_at->gt($originalTimestamp))->toBeTrue();
});
<?php

namespace Tests\Feature;

use App\Jobs\UpdateLineupTimestamp;
use App\Models\Artist;
use App\Models\Lineup;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UpdateLineupTimestampTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_changing_stack_dispatches_update_lineup_timestamp_job()
    {
        Queue::fake();

        $lineup = Lineup::factory()->create();
        $artist = Artist::factory()->create();
        $lineup->artists()->attach($artist->id);

        $this->actingAs($this->user)
            ->post(route('api.lineups.stacks.store', $lineup->id), [
                'artist_id' => $artist->id,
            ]);

        Queue::assertPushed(UpdateLineupTimestamp::class, function ($job) use ($lineup) {
            return $job->lineupId === $lineup->id && $job->delay !== null;
        });
    }

    public function test_job_updates_lineup_timestamp()
    {
        $lineup = Lineup::factory()->create([
            'updated_at' => now()->subHours(1),
        ]);
        $originalTimestamp = $lineup->updated_at;

        $job = new UpdateLineupTimestamp($lineup->id);
        $job->handle();

        $lineup->refresh();
        $this->assertTrue($lineup->updated_at->gt($originalTimestamp));
    }
}

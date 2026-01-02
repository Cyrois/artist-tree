<?php

namespace App\Jobs;

use App\Models\Lineup;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateLineupTimestamp implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 900; // 15 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(public int $lineupId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lineup = Lineup::find($this->lineupId);
        
        if ($lineup) {
            $lineup->touch();
        }
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->lineupId;
    }

    /**
     * Dispatch the job with a 15-minute delay for grouping.
     */
    public static function dispatchGrouped(int $lineupId): void
    {
        static::dispatch($lineupId)->delay(now()->addMinutes(15));
    }
}

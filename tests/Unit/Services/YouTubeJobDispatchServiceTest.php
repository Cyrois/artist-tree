<?php

use App\Jobs\FetchYouTubeDataJob;
use App\Models\Artist;
use App\Models\ArtistMetric;
use App\Services\YouTubeJobDispatchService;
use App\Services\YouTubeService;

use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

describe('YouTubeJobDispatchService', function () {
    it('dispatches priority jobs for artists with stale data', function () {
        // Create artists with different staleness levels
        $highPriorityArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_high_priority',
        ]);
        $highPriorityArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(25), // Stale basic metrics
            'youtube_subscribers' => 1000,
            'refreshed_at' => now(),
        ]);

        $mediumPriorityArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_medium_priority',
        ]);
        $mediumPriorityArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12), // Fresh basic metrics
            'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale analytics
            'youtube_subscribers' => 2000,
            'youtube_avg_views' => 10000, // Has analytics data
            'youtube_videos_analyzed' => 15, // Has analytics data
            'refreshed_at' => now(),
        ]);

        $lowPriorityArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_low_priority',
        ]);
        $lowPriorityArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12), // Fresh basic metrics
            'youtube_subscribers' => 3000, // Has basic data
            'youtube_avg_views' => null, // No analytics yet
            'youtube_videos_analyzed' => null, // No analytics yet
            'youtube_analytics_refreshed_at' => null, // No analytics yet
            'refreshed_at' => now(),
        ]);

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $artistIds = [$highPriorityArtist->id, $mediumPriorityArtist->id, $lowPriorityArtist->id];

        $stats = $service->dispatchPriorityJobs($artistIds, false); // Skip quota check

        expect($stats['high_priority'])->toBe(1)
            ->and($stats['medium_priority'])->toBe(1)
            ->and($stats['low_priority'])->toBe(1)
            ->and($stats['total_jobs'])->toBe(3)
            ->and($stats['total_artists'])->toBe(3);

        Queue::assertPushed(FetchYouTubeDataJob::class, 3);
    });

    it('skips artists without youtube channel ids', function () {
        $artistWithoutYouTube = Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $stats = $service->dispatchPriorityJobs([$artistWithoutYouTube->id], false);

        expect($stats['total_jobs'])->toBe(0)
            ->and($stats['total_artists'])->toBe(0);

        Queue::assertNotPushed(FetchYouTubeDataJob::class);
    });

    it('respects quota availability when requested', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_test',
        ]);

        // Mock YouTube service to return quota exhausted
        $youtubeService = $this->mock(YouTubeService::class, function ($mock) {
            $mock->shouldReceive('checkQuotaAvailability')->andReturn(false);
        });

        $service = new YouTubeJobDispatchService($youtubeService);
        $stats = $service->dispatchPriorityJobs([$artist->id], true); // Respect quota

        expect($stats['total_jobs'])->toBe(0)
            ->and($stats)->toHaveKey('quota_exhausted', true);

        Queue::assertNotPushed(FetchYouTubeDataJob::class);
    });

    it('dispatches basic metrics jobs for artists with stale basic data', function () {
        $staleArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_stale',
        ]);
        $staleArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(25),
            'youtube_subscribers' => 1000,
            'refreshed_at' => now(),
        ]);

        $freshArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_fresh',
        ]);
        $freshArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12),
            'youtube_subscribers' => 2000,
            'refreshed_at' => now(),
        ]);

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $jobCount = $service->dispatchBasicMetricsJobs([$staleArtist->id, $freshArtist->id]);

        expect($jobCount)->toBe(1);

        Queue::assertPushed(FetchYouTubeDataJob::class, function ($job) use ($staleArtist) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('artistIds');
            $property->setAccessible(true);
            $artistIds = $property->getValue($job);

            return in_array($staleArtist->id, $artistIds) && count($artistIds) === 1;
        });
    });

    it('dispatches analytics jobs for artists with stale analytics', function () {
        $staleAnalyticsArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_stale_analytics',
        ]);
        $staleAnalyticsArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12), // Fresh basic metrics
            'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale analytics
            'youtube_subscribers' => 1000,
            'refreshed_at' => now(),
        ]);

        $freshAnalyticsArtist = Artist::factory()->create([
            'youtube_channel_id' => 'UC_fresh_analytics',
        ]);
        $freshAnalyticsArtist->metrics()->create([
            'youtube_refreshed_at' => now()->subHours(12),
            'youtube_analytics_refreshed_at' => now()->subDays(3), // Fresh analytics
            'youtube_subscribers' => 2000,
            'refreshed_at' => now(),
        ]);

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $jobCount = $service->dispatchAnalyticsJobs([$staleAnalyticsArtist->id, $freshAnalyticsArtist->id]);

        expect($jobCount)->toBe(1);

        Queue::assertPushed(FetchYouTubeDataJob::class, function ($job) use ($staleAnalyticsArtist) {
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('artistIds');
            $property->setAccessible(true);
            $artistIds = $property->getValue($job);

            return in_array($staleAnalyticsArtist->id, $artistIds) && count($artistIds) === 1;
        });
    });

    it('batches artists correctly based on priority', function () {
        // Create 25 high priority artists (should create 2 batches: 20 + 5)
        $highPriorityArtists = Artist::factory()->count(25)->create([
            'youtube_channel_id' => 'UC_high',
        ]);

        foreach ($highPriorityArtists as $artist) {
            $artist->metrics()->create([
                'youtube_refreshed_at' => now()->subHours(25), // Stale
                'youtube_subscribers' => 1000,
                'refreshed_at' => now(),
            ]);
        }

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $artistIds = $highPriorityArtists->pluck('id')->toArray();
        $stats = $service->dispatchPriorityJobs($artistIds, false);

        expect($stats['high_priority'])->toBe(2) // 2 batches
            ->and($stats['total_jobs'])->toBe(2);

        Queue::assertPushed(FetchYouTubeDataJob::class, 2);
    });

    it('handles empty artist arrays gracefully', function () {
        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $stats = $service->dispatchPriorityJobs([], false);

        expect($stats['total_jobs'])->toBe(0)
            ->and($stats['total_artists'])->toBe(0);

        Queue::assertNotPushed(FetchYouTubeDataJob::class);
    });

    it('prioritizes artists without metrics as high priority', function () {
        $artistWithoutMetrics = Artist::factory()->create([
            'youtube_channel_id' => 'UC_no_metrics',
        ]);
        // No metrics created

        $youtubeService = $this->mock(YouTubeService::class);
        $youtubeService->shouldReceive('checkQuotaAvailability')->andReturn(true);
        
        $service = new YouTubeJobDispatchService($youtubeService);
        $stats = $service->dispatchPriorityJobs([$artistWithoutMetrics->id], false);

        expect($stats['high_priority'])->toBe(1)
            ->and($stats['medium_priority'])->toBe(0)
            ->and($stats['low_priority'])->toBe(0);

        Queue::assertPushed(FetchYouTubeDataJob::class, 1);
    });
});
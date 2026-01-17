<?php

use App\Models\Artist;
use App\Models\ArtistMetric;

describe('ArtistMetric Model', function () {

    describe('Property 8: Database Update Consistency', function () {
        it('maintains consistent data when updating YouTube metrics', function () {
            // Property 8: For any successful YouTube API response,
            // the system should update the artist_metrics table and record the refresh timestamp

            $artist = Artist::factory()->create();
            $metrics = ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_subscribers' => null,
                'youtube_refreshed_at' => null,
                'youtube_avg_views' => null,
                'youtube_avg_likes' => null,
                'youtube_avg_comments' => null,
                'youtube_videos_analyzed' => null,
                'youtube_analytics_refreshed_at' => null,
            ]);

            // Simulate updating YouTube basic metrics
            $basicUpdateTime = now();
            $metrics->update([
                'youtube_subscribers' => 100000,
                'youtube_refreshed_at' => $basicUpdateTime,
            ]);

            $metrics->refresh();

            expect($metrics->youtube_subscribers)->toBe(100000);
            expect($metrics->youtube_refreshed_at->format('Y-m-d H:i:s'))
                ->toBe($basicUpdateTime->format('Y-m-d H:i:s'));

            // Simulate updating YouTube analytics
            $analyticsUpdateTime = now()->addMinutes(5);
            $metrics->update([
                'youtube_avg_views' => 50000,
                'youtube_avg_likes' => 2500,
                'youtube_avg_comments' => 150,
                'youtube_videos_analyzed' => 10,
                'youtube_analytics_refreshed_at' => $analyticsUpdateTime,
            ]);

            $metrics->refresh();

            expect($metrics->youtube_avg_views)->toBe(50000);
            expect($metrics->youtube_avg_likes)->toBe(2500);
            expect($metrics->youtube_avg_comments)->toBe(150);
            expect($metrics->youtube_videos_analyzed)->toBe(10);
            expect($metrics->youtube_analytics_refreshed_at->format('Y-m-d H:i:s'))
                ->toBe($analyticsUpdateTime->format('Y-m-d H:i:s'));

            // Basic metrics should remain unchanged
            expect($metrics->youtube_subscribers)->toBe(100000);
            expect($metrics->youtube_refreshed_at->format('Y-m-d H:i:s'))
                ->toBe($basicUpdateTime->format('Y-m-d H:i:s'));
        });

        it('handles null values correctly when no YouTube data exists', function () {
            $artist = Artist::factory()->create();
            $metrics = ArtistMetric::factory()->withoutYouTube()->create([
                'artist_id' => $artist->id,
            ]);

            expect($metrics->youtube_subscribers)->toBeNull();
            expect($metrics->youtube_refreshed_at)->toBeNull();
            expect($metrics->youtube_avg_views)->toBeNull();
            expect($metrics->youtube_avg_likes)->toBeNull();
            expect($metrics->youtube_avg_comments)->toBeNull();
            expect($metrics->youtube_videos_analyzed)->toBeNull();
            expect($metrics->youtube_analytics_refreshed_at)->toBeNull();
        });

        it('preserves data integrity when partial updates occur', function () {
            $artist = Artist::factory()->create();
            $metrics = ArtistMetric::factory()->create([
                'artist_id' => $artist->id,
                'youtube_subscribers' => 50000,
                'youtube_refreshed_at' => now()->subHours(2),
                'youtube_avg_views' => 25000,
                'youtube_avg_likes' => 1250,
                'youtube_avg_comments' => 75,
                'youtube_videos_analyzed' => 8,
                'youtube_analytics_refreshed_at' => now()->subDays(2),
            ]);

            $originalAnalyticsTime = $metrics->youtube_analytics_refreshed_at;

            // Update only basic metrics
            $newBasicTime = now();
            $metrics->update([
                'youtube_subscribers' => 75000,
                'youtube_refreshed_at' => $newBasicTime,
            ]);

            $metrics->refresh();

            // Basic metrics should be updated
            expect($metrics->youtube_subscribers)->toBe(75000);
            expect($metrics->youtube_refreshed_at->format('Y-m-d H:i:s'))
                ->toBe($newBasicTime->format('Y-m-d H:i:s'));

            // Analytics should remain unchanged
            expect($metrics->youtube_avg_views)->toBe(25000);
            expect($metrics->youtube_avg_likes)->toBe(1250);
            expect($metrics->youtube_avg_comments)->toBe(75);
            expect($metrics->youtube_videos_analyzed)->toBe(8);
            expect($metrics->youtube_analytics_refreshed_at->format('Y-m-d H:i:s'))
                ->toBe($originalAnalyticsTime->format('Y-m-d H:i:s'));
        });
    });

    describe('Property 13: Automatic Refresh for Stale Data', function () {
        it('correctly identifies stale YouTube basic metrics', function () {
            // Property 13: For any artist request where YouTube data is older than 24 hours,
            // the system should automatically trigger a refresh

            // Fresh YouTube data (within 24 hours)
            $freshArtist = Artist::factory()->create();
            $freshMetrics = ArtistMetric::factory()->create([
                'artist_id' => $freshArtist->id,
                'youtube_refreshed_at' => now()->subHours(12),
            ]);

            expect($freshMetrics->isYouTubeStale())->toBeFalse();
            expect($freshMetrics->needsYouTubeRefresh())->toBeFalse();

            // Stale YouTube data (older than 24 hours)
            $staleArtist = Artist::factory()->create();
            $staleMetrics = ArtistMetric::factory()->create([
                'artist_id' => $staleArtist->id,
                'youtube_refreshed_at' => now()->subHours(25),
            ]);

            expect($staleMetrics->isYouTubeStale())->toBeTrue();
            expect($staleMetrics->needsYouTubeRefresh())->toBeTrue();

            // No YouTube data (null timestamp)
            $noDataArtist = Artist::factory()->create();
            $noDataMetrics = ArtistMetric::factory()->create([
                'artist_id' => $noDataArtist->id,
                'youtube_refreshed_at' => null,
            ]);

            expect($noDataMetrics->isYouTubeStale())->toBeTrue();
            expect($noDataMetrics->needsYouTubeRefresh())->toBeTrue();
        });

        it('correctly identifies stale YouTube analytics', function () {
            // Fresh analytics (within 7 days)
            $freshArtist = Artist::factory()->create();
            $freshAnalytics = ArtistMetric::factory()->create([
                'artist_id' => $freshArtist->id,
                'youtube_analytics_refreshed_at' => now()->subDays(3),
            ]);

            expect($freshAnalytics->isYouTubeAnalyticsStale())->toBeFalse();

            // Stale analytics (older than 7 days)
            $staleArtist = Artist::factory()->create();
            $staleAnalytics = ArtistMetric::factory()->create([
                'artist_id' => $staleArtist->id,
                'youtube_analytics_refreshed_at' => now()->subDays(8),
            ]);

            expect($staleAnalytics->isYouTubeAnalyticsStale())->toBeTrue();
            expect($staleAnalytics->needsYouTubeRefresh())->toBeTrue();

            // No analytics data (null timestamp)
            $noAnalyticsArtist = Artist::factory()->create();
            $noAnalytics = ArtistMetric::factory()->create([
                'artist_id' => $noAnalyticsArtist->id,
                'youtube_analytics_refreshed_at' => null,
            ]);

            expect($noAnalytics->isYouTubeAnalyticsStale())->toBeTrue();
            expect($noAnalytics->needsYouTubeRefresh())->toBeTrue();
        });

        it('handles mixed staleness scenarios correctly', function () {
            // Fresh basic metrics, stale analytics
            $mixedArtist1 = Artist::factory()->create();
            $mixedStale1 = ArtistMetric::factory()->create([
                'artist_id' => $mixedArtist1->id,
                'youtube_refreshed_at' => now()->subHours(12), // Fresh
                'youtube_analytics_refreshed_at' => now()->subDays(8), // Stale
            ]);

            expect($mixedStale1->isYouTubeStale())->toBeFalse();
            expect($mixedStale1->isYouTubeAnalyticsStale())->toBeTrue();
            expect($mixedStale1->needsYouTubeRefresh())->toBeTrue();

            // Stale basic metrics, fresh analytics
            $mixedArtist2 = Artist::factory()->create();
            $mixedStale2 = ArtistMetric::factory()->create([
                'artist_id' => $mixedArtist2->id,
                'youtube_refreshed_at' => now()->subHours(25), // Stale
                'youtube_analytics_refreshed_at' => now()->subDays(3), // Fresh
            ]);

            expect($mixedStale2->isYouTubeStale())->toBeTrue();
            expect($mixedStale2->isYouTubeAnalyticsStale())->toBeFalse();
            expect($mixedStale2->needsYouTubeRefresh())->toBeTrue();

            // Both fresh
            $bothFreshArtist = Artist::factory()->create();
            $bothFresh = ArtistMetric::factory()->create([
                'artist_id' => $bothFreshArtist->id,
                'youtube_refreshed_at' => now()->subHours(12), // Fresh
                'youtube_analytics_refreshed_at' => now()->subDays(3), // Fresh
            ]);

            expect($bothFresh->isYouTubeStale())->toBeFalse();
            expect($bothFresh->isYouTubeAnalyticsStale())->toBeFalse();
            expect($bothFresh->needsYouTubeRefresh())->toBeFalse();
        });

        it('correctly identifies presence of YouTube data', function () {
            // Has YouTube basic data
            $basicDataArtist = Artist::factory()->create();
            $hasBasicData = ArtistMetric::factory()->create([
                'artist_id' => $basicDataArtist->id,
                'youtube_subscribers' => 100000,
                'youtube_avg_views' => null,
                'youtube_videos_analyzed' => null,
            ]);

            expect($hasBasicData->hasYouTubeData())->toBeTrue();
            expect($hasBasicData->hasYouTubeAnalytics())->toBeFalse();

            // Has YouTube analytics data
            $analyticsDataArtist = Artist::factory()->create();
            $hasAnalyticsData = ArtistMetric::factory()->create([
                'artist_id' => $analyticsDataArtist->id,
                'youtube_subscribers' => 100000,
                'youtube_avg_views' => 50000,
                'youtube_videos_analyzed' => 10,
            ]);

            expect($hasAnalyticsData->hasYouTubeData())->toBeTrue();
            expect($hasAnalyticsData->hasYouTubeAnalytics())->toBeTrue();

            // No YouTube data
            $noDataArtist = Artist::factory()->create();
            $noData = ArtistMetric::factory()->withoutYouTube()->create([
                'artist_id' => $noDataArtist->id,
            ]);

            expect($noData->hasYouTubeData())->toBeFalse();
            expect($noData->hasYouTubeAnalytics())->toBeFalse();
        });
    });
});

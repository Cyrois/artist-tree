<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Enums\SocialPlatform;
use App\Jobs\UpdateYoutubeLinksJob;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Services\VEVOChannelDetectionService;
use App\Services\YouTubeChannelRankingAlgorithm;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Tests for UpdateYoutubeLinksJob
 *
 * The job's simplified flow:
 * 1. Check if artist needs YouTube channel update
 * 2. Get all artist's YouTube links
 * 3. Fetch channel data, filter out VEVO channels
 * 4. Rank channels and promote the best one
 */
describe('UpdateYoutubeLinksJob', function () {

    beforeEach(function () {
        Cache::flush();
    });

    describe('Step 0: Check if Artist Needs Update', function () {

        it('skips processing if artist does not need YouTube channel update', function () {
            // Artist has approved YouTube link - does not need update
            $artist = Artist::factory()->create([
                'youtube_channel_id' => 'UCtest123',
            ]);

            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCtest123',
                'review_status' => ArtistLink::REVIEW_STATUS_APPROVED,
            ]);

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            // Should NOT receive any method calls because job exits early
            $detectionService->shouldNotReceive('extractChannelIdFromUrl');
            $detectionService->shouldNotReceive('isVEVOChannel');

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldNotReceive('selectBestChannel');

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldNotReceive('getChannelMetrics');

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            // No exception means job completed without processing
            expect(true)->toBeTrue();
        });
    });

    describe('Step 1: Get YouTube Links', function () {

        it('exits early if artist has no YouTube links', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            // Artist has no links at all

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldNotReceive('extractChannelIdFromUrl');

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldNotReceive('selectBestChannel');

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldNotReceive('getChannelMetrics');

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            // Artist should remain unchanged
            $artist->refresh();
            expect($artist->youtube_channel_id)->toBeNull();
        });
    });

    describe('Step 2: Filter VEVO Channels', function () {

        it('skips VEVO channels when processing links', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            // Artist has two YouTube links: one VEVO, one non-VEVO
            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCvevo123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);
            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCreal456',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $vevoChannel = new YouTubeChannelDTO(
                channelId: 'UCvevo123',
                subscriberCount: 1000000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'ArtistVEVO',
            );

            $realChannel = new YouTubeChannelDTO(
                channelId: 'UCreal456',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')
                ->with('https://www.youtube.com/channel/UCvevo123')
                ->andReturn('UCvevo123');
            $detectionService->shouldReceive('extractChannelIdFromUrl')
                ->with('https://www.youtube.com/channel/UCreal456')
                ->andReturn('UCreal456');
            $detectionService->shouldReceive('isVEVOChannel')
                ->with(Mockery::on(fn ($ch) => $ch->channelId === 'UCvevo123'))
                ->andReturn(true);
            $detectionService->shouldReceive('isVEVOChannel')
                ->with(Mockery::on(fn ($ch) => $ch->channelId === 'UCreal456'))
                ->andReturn(false);

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')
                ->with('UCvevo123')
                ->andReturn($vevoChannel);
            $youtubeService->shouldReceive('getChannelMetrics')
                ->with('UCreal456')
                ->andReturn($realChannel);

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            // Only the non-VEVO channel should be passed to ranking
            $rankingAlgorithm->shouldReceive('selectBestChannel')
                ->with(Mockery::on(function ($channels) {
                    return count($channels) === 1 && $channels[0]->channelId === 'UCreal456';
                }))
                ->andReturn($realChannel);

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            $artist->refresh();
            expect($artist->youtube_channel_id)->toBe('UCreal456');
        });

        it('exits if all channels are VEVO', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCvevo123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $vevoChannel = new YouTubeChannelDTO(
                channelId: 'UCvevo123',
                subscriberCount: 1000000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'ArtistVEVO',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCvevo123');
            $detectionService->shouldReceive('isVEVOChannel')->andReturn(true);

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn($vevoChannel);

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldNotReceive('selectBestChannel');

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            // Artist should remain unchanged
            $artist->refresh();
            expect($artist->youtube_channel_id)->toBeNull();
        });
    });

    describe('Step 3 & 4: Rank and Promote', function () {

        it('promotes the best ranked channel', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCbest789',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $bestChannel = new YouTubeChannelDTO(
                channelId: 'UCbest789',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCbest789');
            $detectionService->shouldReceive('isVEVOChannel')->andReturn(false);

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn($bestChannel);

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldReceive('selectBestChannel')->andReturn($bestChannel);

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            $artist->refresh();
            expect($artist->youtube_channel_id)->toBe('UCbest789');
        });

        it('sets review_status to pending_approval on promoted channel', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCbest789',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $bestChannel = new YouTubeChannelDTO(
                channelId: 'UCbest789',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCbest789');
            $detectionService->shouldReceive('isVEVOChannel')->andReturn(false);

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn($bestChannel);

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldReceive('selectBestChannel')->andReturn($bestChannel);

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            $youtubeLink = $artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->first();

            expect($youtubeLink->review_status)->toBe(ArtistLink::REVIEW_STATUS_PENDING_APPROVAL);
        });

        it('exits if no channels meet minimum requirements', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCsmall123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $smallChannel = new YouTubeChannelDTO(
                channelId: 'UCsmall123',
                subscriberCount: 100, // Below threshold
                videoCount: 5,
                subscriberCountHidden: false,
                title: 'Small Artist',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCsmall123');
            $detectionService->shouldReceive('isVEVOChannel')->andReturn(false);

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn($smallChannel);

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldReceive('selectBestChannel')->andReturn(null);

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            // Artist should remain unchanged
            $artist->refresh();
            expect($artist->youtube_channel_id)->toBeNull();
        });
    });

    describe('Job Configuration', function () {

        it('has exponential backoff configuration', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new UpdateYoutubeLinksJob($artist);

            $backoff = $job->backoff();

            expect($backoff)->toBe([60, 120, 240]);
        });

        it('has max 3 retry attempts', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new UpdateYoutubeLinksJob($artist);

            expect($job->tries)->toBe(3);
        });

        it('has proper job tags for monitoring', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new UpdateYoutubeLinksJob($artist);

            $tags = $job->tags();

            expect($tags)->toContain('youtube-links');
            expect($tags)->toContain('artist:'.$artist->id);
        });

        it('uses configured queue name', function () {
            config(['artist-tree.youtube.queue' => 'custom-queue']);

            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new UpdateYoutubeLinksJob($artist);

            expect($job->queue)->toBe('custom-queue');
        });
    });

    describe('Job Isolation', function () {

        it('jobs are dispatched independently', function () {
            Queue::fake();

            $artist1 = Artist::factory()->create(['youtube_channel_id' => 'UCtest1']);
            $artist2 = Artist::factory()->create(['youtube_channel_id' => 'UCtest2']);

            UpdateYoutubeLinksJob::dispatch($artist1);
            UpdateYoutubeLinksJob::dispatch($artist2);

            Queue::assertPushed(UpdateYoutubeLinksJob::class, 2);
        });
    });

    describe('Deduplication', function () {

        it('skips duplicate channel IDs from multiple links', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => null,
            ]);

            // Same channel ID in two different link formats
            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCsame123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);
            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://youtube.com/channel/UCsame123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);

            $channel = new YouTubeChannelDTO(
                channelId: 'UCsame123',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            );

            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCsame123');
            $detectionService->shouldReceive('isVEVOChannel')->once()->andReturn(false); // Only called once

            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->once()->andReturn($channel); // Only called once

            $rankingAlgorithm = Mockery::mock(YouTubeChannelRankingAlgorithm::class);
            $rankingAlgorithm->shouldReceive('selectBestChannel')
                ->with(Mockery::on(fn ($channels) => count($channels) === 1))
                ->andReturn($channel);

            $job = new UpdateYoutubeLinksJob($artist);
            $job->handle($detectionService, $rankingAlgorithm, $youtubeService);

            $artist->refresh();
            expect($artist->youtube_channel_id)->toBe('UCsame123');
        });
    });
});

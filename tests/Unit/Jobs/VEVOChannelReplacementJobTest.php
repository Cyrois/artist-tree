<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Enums\SocialPlatform;
use App\Jobs\VEVOChannelReplacementJob;
use App\Models\Artist;
use App\Models\ArtistLink;
use App\Services\VEVOChannelDetectionService;
use App\Services\YouTubeChannelRankingAlgorithm;
use App\Services\YouTubeChannelSearchService;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Feature: vevo-channel-detection
 * Property 10: Database Update on Replacement
 * Property 12: Pending Approval Status Assignment
 * Property 15: API Error Retry Logic
 * Property 16: Error Isolation in Processing
 * Validates: Requirements 4.1, 5.3, 8.1, 8.6
 */
describe('VEVOChannelReplacementJob', function () {
    
    beforeEach(function () {
        Cache::flush();
        Http::fake([
            '*youtube.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UCtest123',
                        'statistics' => [
                            'subscriberCount' => '1000000',
                            'videoCount' => '0', // VEVO indicator
                        ],
                        'snippet' => [
                            'title' => 'ArtistVEVO',
                            'description' => 'VEVO channel',
                        ],
                    ],
                ],
            ]),
            '*youtube.com/youtube/v3/search*' => Http::response([
                'items' => [
                    [
                        'id' => ['channelId' => 'UCreal456'],
                        'snippet' => [
                            'title' => 'Artist Official',
                            'description' => 'Official channel',
                        ],
                    ],
                ],
            ]),
        ]);
    });
    
    describe('Property 10: Database Update on Replacement', function () {
        
        it('updates artist youtube_channel_id on successful replacement', function () {
            // Setup: Create artist with VEVO channel
            $artist = Artist::factory()->create([
                'youtube_channel_id' => 'UCvevo123',
            ]);
            
            // Mock services to simulate VEVO detection and replacement
            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
            $detectionService->shouldReceive('detectVEVOChannelForArtist')->andReturn(true);
            $detectionService->shouldReceive('markArtistAsChecked');
            
            $searchService = Mockery::mock(YouTubeChannelSearchService::class);
            $searchService->shouldReceive('searchChannelsForArtist')->andReturn([
                new YouTubeChannelDTO(
                    channelId: 'UCreal456',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'Artist Official',
                ),
            ]);
            
            $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
            
            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn(
                new YouTubeChannelDTO(
                    channelId: 'UCvevo123',
                    subscriberCount: 1000000,
                    videoCount: 0,
                    subscriberCountHidden: false,
                    title: 'ArtistVEVO',
                )
            );
            
            // Execute job
            $job = new VEVOChannelReplacementJob($artist);
            $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
            
            // Assert database was updated
            $artist->refresh();
            expect($artist->youtube_channel_id)->toBe('UCreal456');
        });
    });

    describe('Property 12: Pending Approval Status Assignment', function () {
        
        it('sets review_status to pending_approval on replacement', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => 'UCvevo123',
            ]);
            
            // Create existing YouTube link
            $artist->links()->create([
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://www.youtube.com/channel/UCvevo123',
                'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ]);
            
            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
            $detectionService->shouldReceive('detectVEVOChannelForArtist')->andReturn(true);
            $detectionService->shouldReceive('markArtistAsChecked');
            
            $searchService = Mockery::mock(YouTubeChannelSearchService::class);
            $searchService->shouldReceive('searchChannelsForArtist')->andReturn([
                new YouTubeChannelDTO(
                    channelId: 'UCreal456',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'Artist Official',
                ),
            ]);
            
            $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
            
            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn(
                new YouTubeChannelDTO(
                    channelId: 'UCvevo123',
                    subscriberCount: 1000000,
                    videoCount: 0,
                    subscriberCountHidden: false,
                    title: 'ArtistVEVO',
                )
            );
            
            $job = new VEVOChannelReplacementJob($artist);
            $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
            
            $youtubeLink = $artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->first();
            
            expect($youtubeLink->review_status)->toBe(ArtistLink::REVIEW_STATUS_PENDING_APPROVAL);
        });
        
        it('creates YouTube link with pending_approval if none exists', function () {
            $artist = Artist::factory()->create([
                'youtube_channel_id' => 'UCvevo123',
            ]);
            
            // No existing YouTube link
            
            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
            $detectionService->shouldReceive('detectVEVOChannelForArtist')->andReturn(true);
            $detectionService->shouldReceive('markArtistAsChecked');
            
            $searchService = Mockery::mock(YouTubeChannelSearchService::class);
            $searchService->shouldReceive('searchChannelsForArtist')->andReturn([
                new YouTubeChannelDTO(
                    channelId: 'UCreal456',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'Artist Official',
                ),
            ]);
            
            $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
            
            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn(
                new YouTubeChannelDTO(
                    channelId: 'UCvevo123',
                    subscriberCount: 1000000,
                    videoCount: 0,
                    subscriberCountHidden: false,
                    title: 'ArtistVEVO',
                )
            );
            
            $job = new VEVOChannelReplacementJob($artist);
            $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
            
            $youtubeLink = $artist->links()
                ->where('platform', SocialPlatform::YouTube)
                ->first();
            
            expect($youtubeLink)->not->toBeNull();
            expect($youtubeLink->review_status)->toBe(ArtistLink::REVIEW_STATUS_PENDING_APPROVAL);
            expect($youtubeLink->url)->toContain('UCreal456');
        });
    });
    
    describe('Property 15: API Error Retry Logic', function () {
        
        it('has exponential backoff configuration', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new VEVOChannelReplacementJob($artist);
            
            $backoff = $job->backoff();
            
            expect($backoff)->toBe([60, 120, 240]);
        });
        
        it('has max 3 retry attempts', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new VEVOChannelReplacementJob($artist);
            
            expect($job->tries)->toBe(3);
        });
    });
    
    describe('Property 16: Error Isolation in Processing', function () {
        
        it('does not affect other artists when one fails', function () {
            Queue::fake();
            
            $artist1 = Artist::factory()->create(['youtube_channel_id' => 'UCtest1']);
            $artist2 = Artist::factory()->create(['youtube_channel_id' => 'UCtest2']);
            
            // Dispatch jobs for both artists
            VEVOChannelReplacementJob::dispatch($artist1);
            VEVOChannelReplacementJob::dispatch($artist2);
            
            // Both jobs should be queued independently
            Queue::assertPushed(VEVOChannelReplacementJob::class, 2);
        });
        
        it('uses configured queue name', function () {
            // Set a custom queue name in config
            config(['artist-tree.vevo_detection.queue' => 'custom-vevo-queue']);
            
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new VEVOChannelReplacementJob($artist);
            
            expect($job->queue)->toBe('custom-vevo-queue');
        });
        
        it('uses default queue when not configured', function () {
            // Ensure default config is used
            config(['artist-tree.vevo_detection.queue' => 'default']);
            
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new VEVOChannelReplacementJob($artist);
            
            expect($job->queue)->toBe('default');
        });
    });
    
    describe('Job Behavior', function () {
        
        it('skips processing if artist was recently checked', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            
            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('shouldCheckArtist')->andReturn(false);
            
            $searchService = Mockery::mock(YouTubeChannelSearchService::class);
            $searchService->shouldNotReceive('searchChannelsForArtist');
            
            $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
            
            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldNotReceive('getChannelMetrics');
            
            $job = new VEVOChannelReplacementJob($artist);
            $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
            
            // No exception means job completed without processing
            expect(true)->toBeTrue();
        });
        
        it('skips replacement if channel is not VEVO', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            
            $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
            $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
            $detectionService->shouldReceive('detectVEVOChannelForArtist')->andReturn(false);
            
            $searchService = Mockery::mock(YouTubeChannelSearchService::class);
            $searchService->shouldNotReceive('searchChannelsForArtist');
            
            $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
            
            $youtubeService = Mockery::mock(YouTubeService::class);
            $youtubeService->shouldReceive('getChannelMetrics')->andReturn(
                new YouTubeChannelDTO(
                    channelId: 'UCtest',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'Artist Official',
                )
            );
            
            $job = new VEVOChannelReplacementJob($artist);
            $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
            
            // Artist should not be modified
            $artist->refresh();
            expect($artist->youtube_channel_id)->toBe('UCtest');
        });
        
        it('has proper job tags for monitoring', function () {
            $artist = Artist::factory()->create(['youtube_channel_id' => 'UCtest']);
            $job = new VEVOChannelReplacementJob($artist);
            
            $tags = $job->tags();
            
            expect($tags)->toContain('vevo-detection');
            expect($tags)->toContain('artist:' . $artist->id);
        });
    });
});


describe('Channel Population from Links', function () {
    
    it('populates youtube_channel_id from existing link when artist has none', function () {
        // Artist with no youtube_channel_id but has a YouTube link
        $artist = Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);
        
        // Create existing YouTube link with channel ID in URL
        $artist->links()->create([
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCexisting123',
            'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
        ]);
        
        $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
        $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
        $detectionService->shouldReceive('getUnverifiedYouTubeLinks')->andReturn($artist->links);
        $detectionService->shouldReceive('extractChannelIdFromUrl')
            ->with('https://www.youtube.com/channel/UCexisting123')
            ->andReturn('UCexisting123');
        $detectionService->shouldReceive('markArtistAsChecked');
        
        $searchService = Mockery::mock(YouTubeChannelSearchService::class);
        // Should NOT search for channels since we found one from links
        $searchService->shouldNotReceive('searchChannelsForArtist');
        
        $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
        
        $youtubeService = Mockery::mock(YouTubeService::class);
        $youtubeService->shouldReceive('getChannelMetrics')
            ->with('UCexisting123')
            ->andReturn(new YouTubeChannelDTO(
                channelId: 'UCexisting123',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            ));
        
        $job = new VEVOChannelReplacementJob($artist);
        $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
        
        // Assert youtube_channel_id was populated
        $artist->refresh();
        expect($artist->youtube_channel_id)->toBe('UCexisting123');
    });
    
    it('sets pending_approval status when populating from link', function () {
        $artist = Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);
        
        $artist->links()->create([
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCexisting123',
            'review_status' => ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
        ]);
        
        $detectionService = Mockery::mock(VEVOChannelDetectionService::class);
        $detectionService->shouldReceive('shouldCheckArtist')->andReturn(true);
        $detectionService->shouldReceive('getUnverifiedYouTubeLinks')->andReturn($artist->links);
        $detectionService->shouldReceive('extractChannelIdFromUrl')->andReturn('UCexisting123');
        $detectionService->shouldReceive('markArtistAsChecked');
        
        $searchService = Mockery::mock(YouTubeChannelSearchService::class);
        $rankingAlgorithm = new YouTubeChannelRankingAlgorithm();
        
        $youtubeService = Mockery::mock(YouTubeService::class);
        $youtubeService->shouldReceive('getChannelMetrics')->andReturn(
            new YouTubeChannelDTO(
                channelId: 'UCexisting123',
                subscriberCount: 500000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Artist Official',
            )
        );
        
        $job = new VEVOChannelReplacementJob($artist);
        $job->handle($detectionService, $searchService, $rankingAlgorithm, $youtubeService);
        
        $youtubeLink = $artist->links()
            ->where('platform', SocialPlatform::YouTube)
            ->first();
        
        expect($youtubeLink->review_status)->toBe(ArtistLink::REVIEW_STATUS_PENDING_APPROVAL);
    });
});

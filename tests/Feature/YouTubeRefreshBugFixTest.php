<?php

use App\Models\Artist;
use App\Services\ArtistYouTubeRefreshService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Mock YouTube API responses
    Http::fake([
        'www.googleapis.com/youtube/v3/channels*' => Http::response([
            'items' => [
                [
                    'id' => 'UCTestChannel',
                    'statistics' => [
                        'subscriberCount' => '1500000',
                        'videoCount' => '250'
                    ],
                    'contentDetails' => [
                        'relatedPlaylists' => [
                            'uploads' => 'UUTestChannel'
                        ]
                    ]
                ]
            ]
        ]),
        'www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
            'items' => [
                ['contentDetails' => ['videoId' => 'video1']],
                ['contentDetails' => ['videoId' => 'video2']],
                ['contentDetails' => ['videoId' => 'video3']]
            ]
        ]),
        'www.googleapis.com/youtube/v3/videos*' => Http::response([
            'items' => [
                [
                    'id' => 'video1',
                    'statistics' => [
                        'viewCount' => '100000',
                        'likeCount' => '5000',
                        'commentCount' => '250'
                    ]
                ],
                [
                    'id' => 'video2',
                    'statistics' => [
                        'viewCount' => '200000',
                        'likeCount' => '10000',
                        'commentCount' => '500'
                    ]
                ],
                [
                    'id' => 'video3',
                    'statistics' => [
                        'viewCount' => '150000',
                        'likeCount' => '7500',
                        'commentCount' => '375'
                    ]
                ]
            ]
        ])
    ]);
});

/**
 * Test the bug fix: Artist with YouTube channel ID but no metrics should get refreshed
 */
it('refreshes YouTube data for artist with channel ID but no metrics', function () {
    // Create artist with YouTube channel ID but no metrics
    $artist = new Artist([
        'name' => 'Test Artist',
        'youtube_channel_id' => 'UCTestChannel',
    ]);
    $artist->save();

    // Ensure no metrics exist
    expect($artist->metrics)->toBeNull();

    $service = app(ArtistYouTubeRefreshService::class);
    
    // needsRefresh should return true for artist with channel but no metrics
    expect($service->needsRefresh($artist))->toBeTrue();
    
    // refreshIfNeeded should actually refresh the data
    $result = $service->refreshIfNeeded($artist);
    expect($result)->toBeTrue();

    // Verify metrics were created with YouTube data
    $artist->refresh();
    expect($artist->metrics)->not->toBeNull()
        ->and($artist->metrics->youtube_subscribers)->toBe(1500000)
        ->and($artist->metrics->youtube_refreshed_at)->not->toBeNull()
        ->and($artist->metrics->youtube_avg_views)->toBe(150000) // Average of 100k, 200k, 150k
        ->and($artist->metrics->youtube_analytics_refreshed_at)->not->toBeNull();
});

/**
 * Test that artist without YouTube channel ID is skipped
 */
it('skips artist without YouTube channel ID', function () {
    // Create artist without YouTube channel ID
    $artist = new Artist([
        'name' => 'Test Artist No YouTube',
        'youtube_channel_id' => null,
    ]);
    $artist->save();

    $service = app(ArtistYouTubeRefreshService::class);
    
    // needsRefresh should return false for artist without channel
    expect($service->needsRefresh($artist))->toBeFalse();
    
    // refreshIfNeeded should skip
    $result = $service->refreshIfNeeded($artist);
    expect($result)->toBeFalse();

    // Verify no metrics were created
    $artist->refresh();
    expect($artist->metrics)->toBeNull();
});
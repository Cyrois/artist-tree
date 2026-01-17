<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\DataTransferObjects\YouTubeVideoAnalyticsDTO;

/**
 * Feature: youtube-integration, Property 17: Data Validation and Sanitization
 * Validates: Requirements 8.1, 8.2
 */
it('validates and sanitizes YouTube channel data from API responses', function () {
    // Property 17: Data Validation and Sanitization
    // For any YouTube API response data, the DTO should validate and sanitize
    // subscriber counts and video counts as non-negative integers

    // Test with valid data
    $validData = [
        'id' => 'UCChannelId123',
        'statistics' => [
            'subscriberCount' => '1000000',
            'videoCount' => '500',
            'hiddenSubscriberCount' => false,
        ],
    ];

    $dto = YouTubeChannelDTO::fromYouTubeResponse($validData);

    expect($dto->channelId)->toBe('UCChannelId123')
        ->and($dto->subscriberCount)->toBe(1000000)
        ->and($dto->videoCount)->toBe(500)
        ->and($dto->subscriberCountHidden)->toBe(false)
        ->and($dto->subscriberCount)->toBeGreaterThanOrEqual(0)
        ->and($dto->videoCount)->toBeGreaterThanOrEqual(0);

    // Test with edge case: zero values
    $zeroData = [
        'id' => 'UCZeroChannel',
        'statistics' => [
            'subscriberCount' => '0',
            'videoCount' => '0',
            'hiddenSubscriberCount' => true,
        ],
    ];

    $zeroDto = YouTubeChannelDTO::fromYouTubeResponse($zeroData);

    expect($zeroDto->subscriberCount)->toBe(0)
        ->and($zeroDto->videoCount)->toBe(0)
        ->and($zeroDto->subscriberCountHidden)->toBe(true)
        ->and($zeroDto->subscriberCount)->toBeGreaterThanOrEqual(0)
        ->and($zeroDto->videoCount)->toBeGreaterThanOrEqual(0);

    // Test with missing statistics (should default to 0)
    $missingData = [
        'id' => 'UCMissingStats',
        'statistics' => [],
    ];

    $missingDto = YouTubeChannelDTO::fromYouTubeResponse($missingData);

    expect($missingDto->subscriberCount)->toBe(0)
        ->and($missingDto->videoCount)->toBe(0)
        ->and($missingDto->subscriberCountHidden)->toBe(false)
        ->and($missingDto->subscriberCount)->toBeGreaterThanOrEqual(0)
        ->and($missingDto->videoCount)->toBeGreaterThanOrEqual(0);
});

/**
 * Feature: youtube-integration, Property 17: Data Validation and Sanitization
 * Validates: Requirements 9.3, 9.4, 9.5
 */
it('validates and calculates video analytics from video data', function () {
    // Property 17: Data Validation and Sanitization
    // For any collection of video statistics, the DTO should calculate accurate
    // average metrics and handle edge cases like disabled statistics

    // Test with valid video data
    $validVideos = [
        [
            'id' => 'video1',
            'statistics' => [
                'viewCount' => '1000000',
                'likeCount' => '50000',
                'commentCount' => '5000',
            ],
        ],
        [
            'id' => 'video2',
            'statistics' => [
                'viewCount' => '500000',
                'likeCount' => '25000',
                'commentCount' => '2500',
            ],
        ],
        [
            'id' => 'video3',
            'statistics' => [
                'viewCount' => '2000000',
                'likeCount' => '100000',
                'commentCount' => '10000',
            ],
        ],
    ];

    $dto = YouTubeVideoAnalyticsDTO::calculateFromVideos($validVideos);

    expect($dto->videosAnalyzed)->toBe(3)
        ->and($dto->averageViews)->toBe(1166666.6666666667) // (1000000 + 500000 + 2000000) / 3
        ->and($dto->averageLikes)->toBe(58333.333333333336) // (50000 + 25000 + 100000) / 3
        ->and($dto->averageComments)->toBe(5833.333333333333) // (5000 + 2500 + 10000) / 3
        ->and($dto->videoIds)->toBe(['video1', 'video2', 'video3'])
        ->and($dto->averageViews)->toBeGreaterThanOrEqual(0)
        ->and($dto->averageLikes)->toBeGreaterThanOrEqual(0)
        ->and($dto->averageComments)->toBeGreaterThanOrEqual(0);

    // Test with videos that have disabled statistics (should be excluded)
    $mixedVideos = [
        [
            'id' => 'video1',
            'statistics' => [
                'viewCount' => '1000000',
                'likeCount' => '50000',
                'commentCount' => '5000',
            ],
        ],
        [
            'id' => 'video2',
            'statistics' => [], // Disabled statistics
        ],
        [
            'id' => 'video3',
            'statistics' => [
                'viewCount' => '500000',
                'likeCount' => '25000',
                'commentCount' => '2500',
            ],
        ],
    ];

    $mixedDto = YouTubeVideoAnalyticsDTO::calculateFromVideos($mixedVideos);

    expect($mixedDto->videosAnalyzed)->toBe(2) // Only videos with statistics
        ->and($mixedDto->averageViews)->toBe(750000.0) // (1000000 + 500000) / 2
        ->and($mixedDto->averageLikes)->toBe(37500.0) // (50000 + 25000) / 2
        ->and($mixedDto->averageComments)->toBe(3750.0) // (5000 + 2500) / 2
        ->and($mixedDto->videoIds)->toBe(['video1', 'video3']);

    // Test with empty video array
    $emptyDto = YouTubeVideoAnalyticsDTO::calculateFromVideos([]);

    expect($emptyDto->videosAnalyzed)->toBe(0)
        ->and($emptyDto->averageViews)->toBe(0.0)
        ->and($emptyDto->averageLikes)->toBe(0.0)
        ->and($emptyDto->averageComments)->toBe(0.0)
        ->and($emptyDto->videoIds)->toBe([]);

    // Test with all videos having disabled statistics
    $disabledVideos = [
        [
            'id' => 'video1',
            'statistics' => [],
        ],
        [
            'id' => 'video2',
            'statistics' => [],
        ],
    ];

    $disabledDto = YouTubeVideoAnalyticsDTO::calculateFromVideos($disabledVideos);

    expect($disabledDto->videosAnalyzed)->toBe(0)
        ->and($disabledDto->averageViews)->toBe(0.0)
        ->and($disabledDto->averageLikes)->toBe(0.0)
        ->and($disabledDto->averageComments)->toBe(0.0)
        ->and($disabledDto->videoIds)->toBe([]);
});

/**
 * Feature: youtube-integration, Property 17: Data Validation and Sanitization
 * Validates: Requirements 8.1, 8.2
 */
it('handles missing or invalid data gracefully in DTOs', function () {
    // Property 17: Data Validation and Sanitization
    // For any malformed or incomplete API response, the DTOs should handle
    // the data gracefully without throwing exceptions

    // Test YouTubeChannelDTO with completely missing statistics
    $noStatsData = [
        'id' => 'UCNoStats',
    ];

    $noStatsDto = YouTubeChannelDTO::fromYouTubeResponse($noStatsData);

    expect($noStatsDto->channelId)->toBe('UCNoStats')
        ->and($noStatsDto->subscriberCount)->toBe(0)
        ->and($noStatsDto->videoCount)->toBe(0)
        ->and($noStatsDto->subscriberCountHidden)->toBe(false);

    // Test YouTubeVideoAnalyticsDTO fromYouTubeResponse with missing data
    $incompleteAnalytics = [
        'videos_analyzed' => 5,
        'average_views' => 100000.5,
        // Missing other fields
    ];

    $incompleteDto = YouTubeVideoAnalyticsDTO::fromYouTubeResponse($incompleteAnalytics);

    expect($incompleteDto->videosAnalyzed)->toBe(5)
        ->and($incompleteDto->averageViews)->toBe(100000.5)
        ->and($incompleteDto->averageLikes)->toBe(0.0)
        ->and($incompleteDto->averageComments)->toBe(0.0)
        ->and($incompleteDto->videoIds)->toBe([]);

    // Test with completely empty data
    $emptyAnalytics = [];

    $emptyDto = YouTubeVideoAnalyticsDTO::fromYouTubeResponse($emptyAnalytics);

    expect($emptyDto->videosAnalyzed)->toBe(0)
        ->and($emptyDto->averageViews)->toBe(0.0)
        ->and($emptyDto->averageLikes)->toBe(0.0)
        ->and($emptyDto->averageComments)->toBe(0.0)
        ->and($emptyDto->videoIds)->toBe([]);
});

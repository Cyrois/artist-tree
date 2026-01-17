<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YouTube Channel Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for YouTube channel discovery, ranking, and VEVO detection.
    | VEVO channels are redirect channels with no videos that should be
    | replaced with the artist's primary YouTube channel.
    |
    */
    'youtube' => [
        // Enable/disable YouTube channel processing
        'enabled' => env('YOUTUBE_CHANNEL_ENABLED', true),

        // Days before re-checking an artist for VEVO status
        'vevo_recheck_days' => env('YOUTUBE_VEVO_RECHECK_DAYS', 7),

        // Minimum subscriber count for a valid channel
        'minimum_subscribers' => env('YOUTUBE_MINIMUM_SUBSCRIBERS', 1000),

        // Cache TTL for detection results (seconds)
        'cache_ttl' => env('YOUTUBE_CACHE_TTL', 604800), // 7 days

        // Queue name for YouTube jobs
        'queue' => env('YOUTUBE_QUEUE', 'default'),

        // Maximum retry attempts for failed jobs
        'max_retries' => env('VEVO_MAX_RETRIES', 3),

        // Retry delay in seconds (base for exponential backoff)
        'retry_delay' => env('YOUTUBE_RETRY_DELAY', 60),

        // Verified channel bonus percentage (e.g., 20 = 20% bonus)
        'verified_bonus_percent' => env('YOUTUBE_VERIFIED_BONUS', 20),

        // Recent activity bonus percentage
        'activity_bonus_percent' => env('YOUTUBE_ACTIVITY_BONUS', 10),

        // Official channel name bonus percentage (channels with "official" in name)
        'official_bonus_percent' => env('YOUTUBE_OFFICIAL_BONUS', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metric Weight Presets
    |--------------------------------------------------------------------------
    |
    | Default weight distributions for different scoring strategies.
    | Weights in each preset must sum to 1.00.
    |
    */
    'metric_presets' => [
        'balanced' => [
            'spotify_monthly_listeners' => 0.40,
            'spotify_popularity' => 0.30,
            'youtube_subscribers' => 0.30,
        ],
        'streaming_focused' => [
            'spotify_monthly_listeners' => 0.55,
            'spotify_popularity' => 0.30,
            'youtube_subscribers' => 0.15,
        ],
        'social_media_focused' => [
            'spotify_monthly_listeners' => 0.20,
            'spotify_popularity' => 0.15,
            'youtube_subscribers' => 0.65,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Normalization Maximums
    |--------------------------------------------------------------------------
    |
    | The "max expected" values used for logarithmic normalization.
    | Formula: (log10(value + 1) / log10(max)) * 100
    |
    */
    'normalization_max' => [
        'spotify_monthly_listeners' => 100000000, // 100M
        'spotify_popularity' => 100,           // Already 0-100
        'youtube_subscribers' => 50000000,    // 50M
        'spotify_followers' => 100000000,      // 100M
        'instagram_followers' => 500000000,   // 500M
    ],
];

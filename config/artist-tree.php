<?php

return [
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

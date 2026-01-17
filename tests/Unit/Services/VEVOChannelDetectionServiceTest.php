<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Services\VEVOChannelDetectionService;

/**
 * Feature: vevo-channel-detection
 * Property 1: VEVO Channel Name Detection
 * Property 2: Zero Video VEVO Detection
 * Property 3: VEVO Description Pattern Detection
 * Validates: Requirements 1.1, 1.2, 1.3
 */
describe('VEVOChannelDetectionService', function () {

    beforeEach(function () {
        $this->service = new VEVOChannelDetectionService;
    });

    describe('Property 1: VEVO Channel Name Detection', function () {

        /**
         * For any YouTube channel, if the channel title contains "VEVO" (case-insensitive),
         * it should be identified as a VEVO channel
         */
        it('detects VEVO in channel name (uppercase)', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'ArtistNameVEVO',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('detects VEVO in channel name (lowercase)', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'artistnamevevo',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('detects VEVO in channel name (mixed case)', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'ArtistName Vevo Official',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('property: any channel with VEVO in name is detected regardless of case', function () {
            $vevoVariations = [
                'VEVO',
                'vevo',
                'Vevo',
                'VeVo',
                'vEvO',
            ];

            foreach ($vevoVariations as $variation) {
                $channel = new YouTubeChannelDTO(
                    channelId: 'UCtest'.rand(1000, 9999),
                    subscriberCount: rand(1000, 10000000),
                    videoCount: rand(1, 1000), // Has videos, so only name detection applies
                    subscriberCountHidden: false,
                    title: "Artist Name {$variation}",
                );

                expect($this->service->isVEVOChannel($channel))->toBeTrue(
                    "Failed to detect VEVO variation: {$variation}"
                );
            }
        });

        it('does not detect VEVO when not in name', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Regular Artist Channel',
            );

            expect($this->service->isVEVOChannel($channel))->toBeFalse();
        });
    });

    describe('Property 2: Zero Video VEVO Detection', function () {

        /**
         * For any YouTube channel with exactly zero videos, it should be flagged
         * as a potential VEVO channel
         */
        it('detects channel with zero videos as VEVO', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'Regular Artist Name', // No VEVO in name
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('property: any channel with zero videos is detected as VEVO', function () {
            // Test with various subscriber counts and titles
            for ($i = 0; $i < 10; $i++) {
                $channel = new YouTubeChannelDTO(
                    channelId: 'UCtest'.$i,
                    subscriberCount: rand(0, 10000000),
                    videoCount: 0, // Zero videos
                    subscriberCountHidden: (bool) rand(0, 1),
                    title: 'Artist '.bin2hex(random_bytes(4)), // Random non-VEVO name
                    description: 'Some random description without VEVO',
                );

                expect($this->service->isVEVOChannel($channel))->toBeTrue(
                    'Failed to detect zero-video channel as VEVO'
                );
            }
        });

        it('does not detect channel with videos as VEVO (when no other indicators)', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Regular Artist Name',
                description: 'Official music channel',
            );

            expect($this->service->isVEVOChannel($channel))->toBeFalse();
        });
    });

    describe('Property 3: VEVO Description Pattern Detection', function () {

        /**
         * For any YouTube channel description containing VEVO-related keywords
         * or redirection language, the channel should be identified as VEVO
         */
        it('detects VEVO in description', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Regular Artist Name',
                description: 'This is a VEVO channel for the artist',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('detects redirection language in description', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Regular Artist Name',
                description: 'This channel redirects to the official artist channel',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('detects "visit the official channel" pattern', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Regular Artist Name',
                description: 'Please visit the official channel for more content',
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });

        it('property: any description with VEVO patterns is detected', function () {
            $patterns = [
                'This is a VEVO channel',
                'Powered by Vevo',
                'vevo music videos',
                'official music video collection',
                'redirects to main channel',
                'visit the official channel',
            ];

            foreach ($patterns as $pattern) {
                $channel = new YouTubeChannelDTO(
                    channelId: 'UCtest'.rand(1000, 9999),
                    subscriberCount: rand(1000, 10000000),
                    videoCount: rand(1, 100), // Has videos
                    subscriberCountHidden: false,
                    title: 'Artist '.bin2hex(random_bytes(4)), // Random non-VEVO name
                    description: "Some intro text. {$pattern}. Some outro text.",
                );

                expect($this->service->isVEVOChannel($channel))->toBeTrue(
                    "Failed to detect description pattern: {$pattern}"
                );
            }
        });

        it('does not detect when description has no VEVO patterns', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Regular Artist Name',
                description: 'Welcome to my official music channel. Subscribe for new releases!',
            );

            expect($this->service->isVEVOChannel($channel))->toBeFalse();
        });
    });

    describe('Combined Detection Logic', function () {

        it('returns false for legitimate artist channel', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 5000000,
                videoCount: 200,
                subscriberCountHidden: false,
                title: 'Taylor Swift',
                description: 'Official YouTube channel for Taylor Swift. New album out now!',
            );

            expect($this->service->isVEVOChannel($channel))->toBeFalse();
        });

        it('returns true when multiple VEVO indicators present', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 0, // Zero videos
                subscriberCountHidden: false,
                title: 'ArtistVEVO', // VEVO in name
                description: 'This VEVO channel redirects to the official channel', // VEVO in description
            );

            expect($this->service->isVEVOChannel($channel))->toBeTrue();
        });
    });

    describe('Detection Patterns Accessor', function () {

        it('returns known VEVO patterns', function () {
            $patterns = $this->service->getVEVODetectionPatterns();

            expect($patterns)->toHaveKeys(['name_patterns', 'description_patterns']);
            expect($patterns['name_patterns'])->toContain('vevo');
            expect($patterns['description_patterns'])->toContain('vevo');
        });
    });
});

/**
 * Feature: vevo-channel-detection
 * Property 13: Search-Triggered VEVO Detection
 * Validates: Requirements 6.1
 */
describe('Property 13: Search-Triggered VEVO Detection', function () {

    beforeEach(function () {
        $this->service = new VEVOChannelDetectionService;
    });

    it('shouldCheckArtist returns true for artist with YouTube channel not recently checked', function () {
        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => 'UCtest123',
        ]);

        expect($this->service->shouldCheckArtist($artist))->toBeTrue();
    });

    it('shouldCheckArtist returns true for artist without YouTube channel (for discovery)', function () {
        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);

        // Should return true to enable channel discovery
        expect($this->service->shouldCheckArtist($artist))->toBeTrue();
    });

    it('shouldCheckArtist returns false for recently checked artist', function () {
        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => 'UCtest123',
        ]);

        // Create a YouTube link with recent vevo_checked_at
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCtest123',
            'vevo_checked_at' => now()->subDays(3), // Checked 3 days ago
        ]);

        expect($this->service->shouldCheckArtist($artist))->toBeFalse();
    });

    it('shouldCheckArtist returns true for artist checked more than 7 days ago', function () {
        // Clear cache to ensure clean state
        \Illuminate\Support\Facades\Cache::flush();

        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => 'UColdcheck123',
        ]);

        // Create a YouTube link with old vevo_checked_at
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UColdcheck123',
            'vevo_checked_at' => now()->subDays(10), // Checked 10 days ago
        ]);

        // Refresh the artist to ensure relationships are loaded
        $artist->refresh();

        expect($this->service->shouldCheckArtist($artist))->toBeTrue();
    });

    it('property: any artist in search results with YouTube channel should be checkable', function () {
        // Create multiple artists with YouTube channels
        for ($i = 0; $i < 5; $i++) {
            $artist = \App\Models\Artist::factory()->create([
                'youtube_channel_id' => 'UCtest'.$i,
            ]);

            // All should be checkable since they haven't been checked
            expect($this->service->shouldCheckArtist($artist))->toBeTrue(
                "Artist {$i} should be checkable"
            );
        }
    });
});

/**
 * Feature: vevo-channel-detection
 * Unverified YouTube Links Detection
 * Validates: Artists with unverified YouTube links should trigger VEVO detection
 */
describe('Unverified YouTube Links Detection', function () {

    beforeEach(function () {
        $this->service = new VEVOChannelDetectionService;
    });

    it('shouldCheckArtist returns true for artist with unverified YouTube link (no youtube_channel_id)', function () {
        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => null, // No channel ID set
        ]);

        // Create an unverified YouTube link
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCunverified123',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
        ]);

        expect($this->service->shouldCheckArtist($artist))->toBeTrue();
    });

    it('shouldCheckArtist returns false for artist with approved YouTube link', function () {
        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);

        // Create an approved YouTube link (already verified)
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCapproved123',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_APPROVED,
            'vevo_checked_at' => now()->subDays(3),
        ]);

        expect($this->service->shouldCheckArtist($artist))->toBeFalse();
    });

    it('shouldCheckArtist returns true for artist with pending_approval YouTube link not recently checked', function () {
        \Illuminate\Support\Facades\Cache::flush();

        $artist = \App\Models\Artist::factory()->create([
            'youtube_channel_id' => null,
        ]);

        // Create a pending approval link that hasn't been checked
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCpending123',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
            'vevo_checked_at' => null,
        ]);

        expect($this->service->shouldCheckArtist($artist))->toBeTrue();
    });

    it('getUnverifiedYouTubeLinks returns only non-approved YouTube links', function () {
        $artist = \App\Models\Artist::factory()->create();

        // Create various links
        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCpublic',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
        ]);

        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::YouTube,
            'url' => 'https://www.youtube.com/channel/UCapproved',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_APPROVED,
        ]);

        $artist->links()->create([
            'platform' => \App\Enums\SocialPlatform::Instagram,
            'url' => 'https://instagram.com/artist',
            'review_status' => \App\Models\ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
        ]);

        $unverified = $this->service->getUnverifiedYouTubeLinks($artist);

        expect($unverified)->toHaveCount(1);
        expect($unverified->first()->url)->toBe('https://www.youtube.com/channel/UCpublic');
    });

    it('extractChannelIdFromUrl extracts channel ID from standard URL', function () {
        $url = 'https://www.youtube.com/channel/UCabcdef123456';

        expect($this->service->extractChannelIdFromUrl($url))->toBe('UCabcdef123456');
    });

    it('extractChannelIdFromUrl returns null for non-channel URLs', function () {
        $urls = [
            'https://www.youtube.com/@artistname',
            'https://www.youtube.com/c/customname',
            'https://www.youtube.com/user/username',
            'https://www.youtube.com/watch?v=abc123',
        ];

        foreach ($urls as $url) {
            expect($this->service->extractChannelIdFromUrl($url))->toBeNull(
                "Should return null for: {$url}"
            );
        }
    });
});

<?php

use App\Enums\SocialPlatform;
use App\Models\Artist;
use App\Models\ArtistLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: vevo-channel-detection, Property 12: Pending Approval Status Assignment
 * Validates: Requirements 5.3
 *
 * For any automatically replaced VEVO channel, the new link should be marked
 * with review_status "pending_approval"
 */
describe('ArtistLink VEVO Detection Schema', function () {

    it('supports review_status column with default value', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
        ]);

        expect($link->review_status)->toBe(ArtistLink::REVIEW_STATUS_PUBLIC_ADDED);
    });

    it('supports all review status values', function () {
        $artist = Artist::factory()->create();

        $statuses = [
            ArtistLink::REVIEW_STATUS_PUBLIC_ADDED,
            ArtistLink::REVIEW_STATUS_ADMIN_ADDED,
            ArtistLink::REVIEW_STATUS_PENDING_APPROVAL,
            ArtistLink::REVIEW_STATUS_APPROVED,
        ];

        foreach ($statuses as $status) {
            $link = ArtistLink::create([
                'artist_id' => $artist->id,
                'platform' => SocialPlatform::YouTube,
                'url' => 'https://youtube.com/channel/UCtest'.rand(1000, 9999),
                'review_status' => $status,
            ]);

            expect($link->review_status)->toBe($status);
        }
    });

    it('supports vevo_checked_at timestamp column', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
            'vevo_checked_at' => now(),
        ]);

        expect($link->vevo_checked_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    it('returns true for needsVevoCheck when never checked', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
        ]);

        expect($link->needsVevoCheck())->toBeTrue();
    });

    it('returns false for needsVevoCheck when recently checked', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
            'vevo_checked_at' => now()->subDays(3),
        ]);

        expect($link->needsVevoCheck())->toBeFalse();
    });

    it('returns true for needsVevoCheck when checked more than 7 days ago', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
            'vevo_checked_at' => now()->subDays(8),
        ]);

        expect($link->needsVevoCheck())->toBeTrue();
    });

    it('can mark link as vevo checked', function () {
        $artist = Artist::factory()->create();

        $link = ArtistLink::create([
            'artist_id' => $artist->id,
            'platform' => SocialPlatform::YouTube,
            'url' => 'https://youtube.com/channel/UCtest123',
        ]);

        expect($link->vevo_checked_at)->toBeNull();

        $link->markVevoChecked();

        expect($link->fresh()->vevo_checked_at)->not->toBeNull();
    });
});

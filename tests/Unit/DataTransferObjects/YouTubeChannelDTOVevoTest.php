<?php

use App\DataTransferObjects\YouTubeChannelDTO;

/**
 * Feature: vevo-channel-detection, Property 17: Replacement Quality Validation
 * Feature: vevo-channel-detection, Property 18: Recent Activity Validation
 * Validates: Requirements 9.1, 9.2
 */
describe('YouTubeChannelDTO VEVO Detection Extensions', function () {
    
    describe('Minimum Subscriber Threshold', function () {
        
        it('returns true when subscriber count meets default threshold', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000,
                videoCount: 50,
                subscriberCountHidden: false,
            );
            
            expect($dto->meetsMinimumSubscriberThreshold())->toBeTrue();
        });
        
        it('returns false when subscriber count is below default threshold', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 999,
                videoCount: 50,
                subscriberCountHidden: false,
            );
            
            expect($dto->meetsMinimumSubscriberThreshold())->toBeFalse();
        });
        
        it('supports custom threshold values', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 5000,
                videoCount: 50,
                subscriberCountHidden: false,
            );
            
            expect($dto->meetsMinimumSubscriberThreshold(5000))->toBeTrue();
            expect($dto->meetsMinimumSubscriberThreshold(5001))->toBeFalse();
        });
        
        /**
         * Property 17: Replacement Quality Validation
         * For any proposed replacement channel, it should have significantly more subscribers
         */
        it('property: channels with zero subscribers never meet threshold', function () {
            // Test with multiple random thresholds
            for ($i = 0; $i < 10; $i++) {
                $threshold = rand(1, 10000);
                
                $dto = new YouTubeChannelDTO(
                    channelId: 'UCtest' . $i,
                    subscriberCount: 0,
                    videoCount: rand(0, 100),
                    subscriberCountHidden: false,
                );
                
                expect($dto->meetsMinimumSubscriberThreshold($threshold))->toBeFalse();
            }
        });
    });
    
    describe('Active Content Validation', function () {
        
        it('returns false when video count is zero', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 10000,
                videoCount: 0,
                subscriberCountHidden: false,
            );
            
            expect($dto->hasActiveContent())->toBeFalse();
        });
        
        it('returns true when has videos and no upload date info', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 10000,
                videoCount: 50,
                subscriberCountHidden: false,
            );
            
            expect($dto->hasActiveContent())->toBeTrue();
        });
        
        it('returns true when last upload is within threshold', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 10000,
                videoCount: 50,
                subscriberCountHidden: false,
                lastUploadDate: now()->subMonths(6),
            );
            
            expect($dto->hasActiveContent(12))->toBeTrue();
        });
        
        it('returns false when last upload is beyond threshold', function () {
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 10000,
                videoCount: 50,
                subscriberCountHidden: false,
                lastUploadDate: now()->subMonths(13),
            );
            
            expect($dto->hasActiveContent(12))->toBeFalse();
        });
        
        /**
         * Property 18: Recent Activity Validation
         * For any replacement channel, it should have uploaded video content within the last 12 months
         */
        it('property: channels with zero videos are never considered active', function () {
            // Test with multiple random configurations
            for ($i = 0; $i < 10; $i++) {
                $monthsThreshold = rand(1, 24);
                $lastUploadDate = rand(0, 1) ? now()->subMonths(rand(1, 6)) : null;
                
                $dto = new YouTubeChannelDTO(
                    channelId: 'UCtest' . $i,
                    subscriberCount: rand(1000, 100000),
                    videoCount: 0,
                    subscriberCountHidden: false,
                    lastUploadDate: $lastUploadDate,
                );
                
                expect($dto->hasActiveContent($monthsThreshold))->toBeFalse();
            }
        });
    });
    
    describe('Factory Methods', function () {
        
        it('creates DTO from YouTube API response with all fields', function () {
            $response = [
                'id' => 'UCtest123',
                'statistics' => [
                    'subscriberCount' => '50000',
                    'videoCount' => '100',
                    'hiddenSubscriberCount' => false,
                ],
                'contentDetails' => [
                    'relatedPlaylists' => [
                        'uploads' => 'UUtest123',
                    ],
                ],
                'snippet' => [
                    'title' => 'Test Artist',
                    'description' => 'Official channel for Test Artist',
                    'customUrl' => '@testartist',
                ],
                'status' => [
                    'isLinked' => true,
                ],
            ];
            
            $dto = YouTubeChannelDTO::fromYouTubeResponse($response);
            
            expect($dto->channelId)->toBe('UCtest123');
            expect($dto->subscriberCount)->toBe(50000);
            expect($dto->videoCount)->toBe(100);
            expect($dto->title)->toBe('Test Artist');
            expect($dto->description)->toBe('Official channel for Test Artist');
        });
        
    });
    

    
    describe('Array Conversion', function () {
        
        it('converts to array with all fields', function () {
            $lastUpload = now()->subDays(30);
            
            $dto = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 50000,
                videoCount: 100,
                subscriberCountHidden: false,
                uploadsPlaylistId: 'UUtest123',
                title: 'Test Channel',
                description: 'Test description',
                lastUploadDate: $lastUpload,
            );
            
            $array = $dto->toArray();
            
            expect($array['channel_id'])->toBe('UCtest123');
            expect($array['subscriber_count'])->toBe(50000);
            expect($array['video_count'])->toBe(100);
            expect($array['title'])->toBe('Test Channel');
            expect($array['description'])->toBe('Test description');
            expect($array['last_upload_date'])->toBe($lastUpload->format('Y-m-d H:i:s'));
        });
    });
});

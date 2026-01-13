<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Services\YouTubeChannelRankingAlgorithm;

/**
 * Feature: vevo-channel-detection
 * Property 7: Subscriber Count Ranking Priority
 * Property 8: Verified Channel Ranking Bonus
 * Property 9: Verified Channel Tie-Breaking
 * Property 11: Minimum Subscriber Threshold
 * Validates: Requirements 3.1, 3.2, 3.6, 4.5
 */
describe('YouTubeChannelRankingAlgorithm', function () {
    
    beforeEach(function () {
        $this->algorithm = new YouTubeChannelRankingAlgorithm();
    });
    
    describe('Property 7: Subscriber Count Ranking Priority', function () {
        
        it('ranks channels by subscriber count (highest first)', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UClow',
                    subscriberCount: 10000,
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Low Subs Channel',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UChigh',
                    subscriberCount: 1000000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'High Subs Channel',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCmid',
                    subscriberCount: 100000,
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Mid Subs Channel',
                ),
            ];
            
            $ranked = $this->algorithm->rankChannels($channels);
            
            expect($ranked[0]->channelId)->toBe('UChigh');
            expect($ranked[1]->channelId)->toBe('UCmid');
            expect($ranked[2]->channelId)->toBe('UClow');
        });
        
        it('property: higher subscriber count always ranks higher (without bonuses)', function () {
            // Generate random channels with varying subscriber counts
            for ($i = 0; $i < 10; $i++) {
                $lowSubs = rand(1000, 50000);
                $highSubs = $lowSubs + rand(10000, 100000);
                
                $channels = [
                    new YouTubeChannelDTO(
                        channelId: 'UClow',
                        subscriberCount: $lowSubs,
                        videoCount: 50,
                        subscriberCountHidden: false,
                        title: 'Low Channel',
                    ),
                    new YouTubeChannelDTO(
                        channelId: 'UChigh',
                        subscriberCount: $highSubs,
                        videoCount: 50,
                        subscriberCountHidden: false,
                        title: 'High Channel',
                    ),
                ];
                
                $ranked = $this->algorithm->rankChannels($channels);
                
                expect($ranked[0]->channelId)->toBe('UChigh',
                    "Channel with {$highSubs} subs should rank above {$lowSubs} subs"
                );
            }
        });
        
        it('calculates base score from subscriber count', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest',
                subscriberCount: 50000,
                videoCount: 0, // No videos, so no activity bonus
                subscriberCountHidden: false,
                title: 'Test Channel',
            );
            
            $score = $this->algorithm->calculateChannelScore($channel);
            
            // Base score should be subscriber count (no bonuses applied)
            expect($score)->toBe(50000.0);
        });
    });

    describe('Property 8: Verified Channel Ranking Bonus', function () {
        
        it('gives 20% bonus to verified channels', function () {
            $unverified = new YouTubeChannelDTO(
                channelId: 'UCunverified',
                subscriberCount: 100000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Unverified Channel',
                isVerified: false,
            );
            
            $verified = new YouTubeChannelDTO(
                channelId: 'UCverified',
                subscriberCount: 100000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Verified Channel',
                isVerified: true,
            );
            
            $unverifiedScore = $this->algorithm->calculateChannelScore($unverified);
            $verifiedScore = $this->algorithm->calculateChannelScore($verified);
            
            // Verified should have 20% higher score
            expect($verifiedScore)->toBe($unverifiedScore * 1.2);
        });
        
        it('verified channel with fewer subs can outrank unverified', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UCunverified',
                    subscriberCount: 100000,
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Unverified Channel',
                    isVerified: false,
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCverified',
                    subscriberCount: 90000, // 10% fewer subs
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Verified Channel',
                    isVerified: true,
                ),
            ];
            
            $ranked = $this->algorithm->rankChannels($channels);
            
            // Verified channel should rank higher due to 20% bonus
            // 90000 * 1.2 = 108000 > 100000
            expect($ranked[0]->channelId)->toBe('UCverified');
        });
    });
    
    describe('Property 9: Verified Channel Tie-Breaking', function () {
        
        it('breaks tie in favor of verified channel', function () {
            $unverified = new YouTubeChannelDTO(
                channelId: 'UCunverified',
                subscriberCount: 100000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Unverified Channel',
                isVerified: false,
            );
            
            $verified = new YouTubeChannelDTO(
                channelId: 'UCverified',
                subscriberCount: 100000, // Same subscriber count
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Verified Channel',
                isVerified: true,
            );
            
            $winner = $this->algorithm->breakTie($unverified, $verified);
            
            expect($winner->channelId)->toBe('UCverified');
        });
        
        it('detects similar subscriber counts within 10%', function () {
            $channelA = new YouTubeChannelDTO(
                channelId: 'UCA',
                subscriberCount: 100000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Channel A',
            );
            
            $channelB = new YouTubeChannelDTO(
                channelId: 'UCB',
                subscriberCount: 95000, // 5% difference
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Channel B',
            );
            
            expect($this->algorithm->haveSimilarSubscriberCounts($channelA, $channelB))->toBeTrue();
        });
        
        it('detects non-similar subscriber counts beyond 10%', function () {
            $channelA = new YouTubeChannelDTO(
                channelId: 'UCA',
                subscriberCount: 100000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Channel A',
            );
            
            $channelB = new YouTubeChannelDTO(
                channelId: 'UCB',
                subscriberCount: 80000, // 20% difference
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Channel B',
            );
            
            expect($this->algorithm->haveSimilarSubscriberCounts($channelA, $channelB))->toBeFalse();
        });
        
        it('property: verified channel wins when subscriber counts are within 10%', function () {
            for ($i = 0; $i < 10; $i++) {
                $baseSubs = rand(10000, 1000000);
                $variation = (int) ($baseSubs * rand(0, 10) / 100); // 0-10% variation
                
                $unverified = new YouTubeChannelDTO(
                    channelId: 'UCunverified',
                    subscriberCount: $baseSubs,
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Unverified',
                    isVerified: false,
                );
                
                $verified = new YouTubeChannelDTO(
                    channelId: 'UCverified',
                    subscriberCount: $baseSubs - $variation, // Slightly fewer subs
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Verified',
                    isVerified: true,
                );
                
                if ($this->algorithm->haveSimilarSubscriberCounts($unverified, $verified)) {
                    $winner = $this->algorithm->breakTie($unverified, $verified);
                    expect($winner->channelId)->toBe('UCverified',
                        "Verified channel should win tie with similar subscriber counts"
                    );
                }
            }
        });
    });

    describe('Property 11: Minimum Subscriber Threshold', function () {
        
        it('excludes channels below 1000 subscribers', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UClow',
                    subscriberCount: 500, // Below threshold
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Low Subs Channel',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCvalid',
                    subscriberCount: 5000, // Above threshold
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Valid Channel',
                ),
            ];
            
            $ranked = $this->algorithm->rankChannels($channels);
            
            expect($ranked)->toHaveCount(1);
            expect($ranked[0]->channelId)->toBe('UCvalid');
        });
        
        it('returns zero score for channels below threshold', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UClow',
                subscriberCount: 500,
                videoCount: 50,
                subscriberCountHidden: false,
                title: 'Low Subs Channel',
            );
            
            $score = $this->algorithm->calculateChannelScore($channel);
            
            expect($score)->toBe(0.0);
        });
        
        it('returns null when no channels meet threshold', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UClow1',
                    subscriberCount: 100,
                    videoCount: 10,
                    subscriberCountHidden: false,
                    title: 'Very Low Channel 1',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UClow2',
                    subscriberCount: 500,
                    videoCount: 20,
                    subscriberCountHidden: false,
                    title: 'Very Low Channel 2',
                ),
            ];
            
            $best = $this->algorithm->selectBestChannel($channels);
            
            expect($best)->toBeNull();
        });
        
        it('property: no channel below 1000 subscribers is ever selected', function () {
            for ($i = 0; $i < 10; $i++) {
                $channels = [];
                
                // Add channels below threshold
                for ($j = 0; $j < 5; $j++) {
                    $channels[] = new YouTubeChannelDTO(
                        channelId: 'UClow' . $j,
                        subscriberCount: rand(1, 999),
                        videoCount: rand(10, 100),
                        subscriberCountHidden: false,
                        title: 'Low Channel ' . $j,
                    );
                }
                
                $best = $this->algorithm->selectBestChannel($channels);
                
                expect($best)->toBeNull(
                    "No channel below 1000 subscribers should be selected"
                );
            }
        });
        
        it('exposes minimum threshold value', function () {
            expect($this->algorithm->getMinimumSubscriberThreshold())->toBe(1000);
        });
    });
    
    describe('Best Channel Selection', function () {
        
        it('selects channel with highest score', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UClow',
                    subscriberCount: 10000,
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Low Channel',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UChigh',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'High Channel',
                ),
            ];
            
            $best = $this->algorithm->selectBestChannel($channels);
            
            expect($best)->not->toBeNull();
            expect($best->channelId)->toBe('UChigh');
        });
        
        it('returns null for empty array', function () {
            $best = $this->algorithm->selectBestChannel([]);
            
            expect($best)->toBeNull();
        });
    });
    
    describe('Replacement Validation', function () {
        
        it('validates replacement meets minimum threshold', function () {
            $replacement = new YouTubeChannelDTO(
                channelId: 'UCnew',
                subscriberCount: 5000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'New Channel',
            );
            
            expect($this->algorithm->isValidReplacement($replacement))->toBeTrue();
        });
        
        it('rejects replacement below minimum threshold', function () {
            $replacement = new YouTubeChannelDTO(
                channelId: 'UCnew',
                subscriberCount: 500,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'New Channel',
            );
            
            expect($this->algorithm->isValidReplacement($replacement))->toBeFalse();
        });
        
        it('validates replacement with content against VEVO original', function () {
            $original = new YouTubeChannelDTO(
                channelId: 'UCvevo',
                subscriberCount: 1000000,
                videoCount: 0, // VEVO channel with no videos
                subscriberCountHidden: false,
                title: 'ArtistVEVO',
            );
            
            $replacement = new YouTubeChannelDTO(
                channelId: 'UCreal',
                subscriberCount: 200000, // 20% of original
                videoCount: 150, // Has actual content
                subscriberCountHidden: false,
                title: 'Artist Official',
            );
            
            expect($this->algorithm->isValidReplacement($replacement, $original))->toBeTrue();
        });
    });
    
    describe('Recent Activity Bonus', function () {
        
        it('gives 10% bonus for recent activity', function () {
            $inactive = new YouTubeChannelDTO(
                channelId: 'UCinactive',
                subscriberCount: 100000,
                videoCount: 0, // No videos = no activity bonus
                subscriberCountHidden: false,
                title: 'Inactive Channel',
                hasRecentActivity: false,
            );
            
            $active = new YouTubeChannelDTO(
                channelId: 'UCactive',
                subscriberCount: 100000,
                videoCount: 0, // No videos, but hasRecentActivity flag is true
                subscriberCountHidden: false,
                title: 'Active Channel',
                hasRecentActivity: true,
            );
            
            $inactiveScore = $this->algorithm->calculateChannelScore($inactive);
            $activeScore = $this->algorithm->calculateChannelScore($active);
            
            // Active should have 10% higher score
            expect($activeScore)->toBe($inactiveScore * 1.1);
        });
    });

    describe('Official Channel Name Bonus', function () {
        
        it('gives 15% bonus for channels with "official" in name', function () {
            $regular = new YouTubeChannelDTO(
                channelId: 'UCregular',
                subscriberCount: 100000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'Artist Name',
            );
            
            $official = new YouTubeChannelDTO(
                channelId: 'UCofficial',
                subscriberCount: 100000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'Artist Name Official',
            );
            
            $regularScore = $this->algorithm->calculateChannelScore($regular);
            $officialScore = $this->algorithm->calculateChannelScore($official);
            
            // Official should have 15% higher score
            expect($officialScore)->toBe($regularScore * 1.15);
        });
        
        it('detects "official" case-insensitively', function () {
            $variations = [
                'Artist Official Channel',
                'Artist OFFICIAL Channel',
                'Official Artist Channel',
                'The official channel',
            ];
            
            foreach ($variations as $title) {
                $channel = new YouTubeChannelDTO(
                    channelId: 'UCtest',
                    subscriberCount: 100000,
                    videoCount: 0,
                    subscriberCountHidden: false,
                    title: $title,
                );
                
                expect($this->algorithm->hasOfficialInName($channel))->toBeTrue(
                    "Should detect 'official' in: {$title}"
                );
            }
        });
        
        it('returns false for channels without "official"', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest',
                subscriberCount: 100000,
                videoCount: 0,
                subscriberCountHidden: false,
                title: 'Artist Name Music',
            );
            
            expect($this->algorithm->hasOfficialInName($channel))->toBeFalse();
        });
        
        it('official channel with fewer subs can outrank regular channel', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UCregular',
                    subscriberCount: 100000,
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Artist Name',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCofficial',
                    subscriberCount: 90000, // 10% fewer subs
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Artist Name Official',
                ),
            ];
            
            $ranked = $this->algorithm->rankChannels($channels);
            
            // Official channel should rank higher due to 15% bonus
            // 90000 * 1.15 = 103500 > 100000
            expect($ranked[0]->channelId)->toBe('UCofficial');
        });
    });
});

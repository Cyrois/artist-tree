<?php

use App\DataTransferObjects\YouTubeChannelDTO;
use App\Models\Artist;
use App\Models\ArtistAlias;
use App\Services\VEVOChannelDetectionService;
use App\Services\YouTubeChannelSearchService;
use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Feature: vevo-channel-detection
 * Property 4: Artist Name Search Query
 * Property 5: Artist Alias Search Coverage
 * Property 6: VEVO Channel Exclusion from Candidates
 * Validates: Requirements 2.1, 2.2, 2.3
 */
describe('YouTubeChannelSearchService', function () {
    
    beforeEach(function () {
        Cache::flush();
        
        $this->vevoDetectionService = new VEVOChannelDetectionService();
        $this->youtubeService = app(YouTubeService::class);
        $this->service = new YouTubeChannelSearchService(
            $this->vevoDetectionService,
            $this->youtubeService,
        );
    });
    
    describe('Property 4: Artist Name Search Query', function () {
        
        it('searches using artist primary name', function () {
            Http::fake([
                '*youtube.com/youtube/v3/search*' => Http::response([
                    'items' => [
                        [
                            'id' => ['channelId' => 'UCtest123'],
                            'snippet' => [
                                'title' => 'Taylor Swift',
                                'description' => 'Official channel',
                            ],
                        ],
                    ],
                ]),
                '*youtube.com/youtube/v3/channels*' => Http::response([
                    'items' => [
                        [
                            'id' => 'UCtest123',
                            'statistics' => [
                                'subscriberCount' => '50000000',
                                'videoCount' => '200',
                            ],
                        ],
                    ],
                ]),
            ]);
            
            $artist = Artist::factory()->create(['name' => 'Taylor Swift']);
            
            $results = $this->service->searchByQuery($artist->name);
            
            expect($results)->toBeArray();
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'search') &&
                       str_contains($request->url(), 'Taylor+Swift') ||
                       str_contains($request->url(), 'Taylor%20Swift');
            });
        });
        
        it('returns empty array for empty query', function () {
            $results = $this->service->searchByQuery('');
            
            expect($results)->toBeEmpty();
        });
        
        it('returns empty array for whitespace-only query', function () {
            $results = $this->service->searchByQuery('   ');
            
            expect($results)->toBeEmpty();
        });
    });

    describe('Property 5: Artist Alias Search Coverage', function () {
        
        it('searches using artist aliases', function () {
            $searchCalls = [];
            
            Http::fake(function ($request) use (&$searchCalls) {
                if (str_contains($request->url(), 'search')) {
                    $searchCalls[] = $request->url();
                    return Http::response([
                        'items' => [
                            [
                                'id' => ['channelId' => 'UCtest' . count($searchCalls)],
                                'snippet' => [
                                    'title' => 'Test Channel',
                                    'description' => 'Official channel',
                                ],
                            ],
                        ],
                    ]);
                }
                
                if (str_contains($request->url(), 'channels')) {
                    return Http::response([
                        'items' => [
                            [
                                'id' => 'UCtest1',
                                'statistics' => [
                                    'subscriberCount' => '50000',
                                    'videoCount' => '100',
                                ],
                            ],
                            [
                                'id' => 'UCtest2',
                                'statistics' => [
                                    'subscriberCount' => '30000',
                                    'videoCount' => '50',
                                ],
                            ],
                        ],
                    ]);
                }
                
                return Http::response([]);
            });
            
            $artist = Artist::factory()->create(['name' => 'The Weeknd']);
            ArtistAlias::create([
                'artist_id' => $artist->id,
                'name' => 'Abel Tesfaye',
            ]);
            
            $results = $this->service->searchChannelsForArtist($artist);
            
            // Should have made at least 2 search calls (primary name + alias)
            expect(count($searchCalls))->toBeGreaterThanOrEqual(2);
        });
        
        it('property: searches for each alias separately', function () {
            $searchQueries = [];
            
            Http::fake(function ($request) use (&$searchQueries) {
                if (str_contains($request->url(), 'search')) {
                    parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);
                    if (isset($params['q'])) {
                        $searchQueries[] = $params['q'];
                    }
                    return Http::response(['items' => []]);
                }
                return Http::response(['items' => []]);
            });
            
            $artist = Artist::factory()->create(['name' => 'Eminem']);
            ArtistAlias::create(['artist_id' => $artist->id, 'name' => 'Slim Shady']);
            ArtistAlias::create(['artist_id' => $artist->id, 'name' => 'Marshall Mathers']);
            
            $this->service->searchChannelsForArtist($artist);
            
            // Should search for primary name and all aliases
            expect($searchQueries)->toContain('Eminem');
            expect($searchQueries)->toContain('Slim Shady');
            expect($searchQueries)->toContain('Marshall Mathers');
        });
    });
    
    describe('Property 6: VEVO Channel Exclusion from Candidates', function () {
        
        it('filters out VEVO channels from results', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UCvevo123',
                    subscriberCount: 1000000,
                    videoCount: 0, // Zero videos = VEVO indicator
                    subscriberCountHidden: false,
                    title: 'ArtistVEVO',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCreal456',
                    subscriberCount: 500000,
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'Artist Official',
                ),
            ];
            
            $filtered = $this->service->filterVEVOChannels($channels);
            
            expect($filtered)->toHaveCount(1);
            expect($filtered[0]->channelId)->toBe('UCreal456');
        });
        
        it('filters out channels with VEVO in name', function () {
            $channels = [
                new YouTubeChannelDTO(
                    channelId: 'UCvevo1',
                    subscriberCount: 1000000,
                    videoCount: 100,
                    subscriberCountHidden: false,
                    title: 'TaylorSwiftVEVO',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCvevo2',
                    subscriberCount: 800000,
                    videoCount: 50,
                    subscriberCountHidden: false,
                    title: 'Taylor Swift Vevo',
                ),
                new YouTubeChannelDTO(
                    channelId: 'UCreal',
                    subscriberCount: 500000,
                    videoCount: 200,
                    subscriberCountHidden: false,
                    title: 'Taylor Swift',
                ),
            ];
            
            $filtered = $this->service->filterVEVOChannels($channels);
            
            expect($filtered)->toHaveCount(1);
            expect($filtered[0]->title)->toBe('Taylor Swift');
        });
        
        it('property: no VEVO channels remain after filtering', function () {
            // Generate random mix of VEVO and non-VEVO channels
            $channels = [];
            
            // Add some VEVO channels
            for ($i = 0; $i < 5; $i++) {
                $channels[] = new YouTubeChannelDTO(
                    channelId: 'UCvevo' . $i,
                    subscriberCount: rand(100000, 10000000),
                    videoCount: 0, // Zero videos = VEVO
                    subscriberCountHidden: false,
                    title: 'Artist' . $i . 'VEVO',
                );
            }
            
            // Add some legitimate channels
            for ($i = 0; $i < 5; $i++) {
                $channels[] = new YouTubeChannelDTO(
                    channelId: 'UCreal' . $i,
                    subscriberCount: rand(10000, 1000000),
                    videoCount: rand(50, 500),
                    subscriberCountHidden: false,
                    title: 'Artist ' . $i . ' Official',
                );
            }
            
            $filtered = $this->service->filterVEVOChannels($channels);
            
            // Verify no VEVO channels remain
            foreach ($filtered as $channel) {
                expect($this->vevoDetectionService->isVEVOChannel($channel))->toBeFalse(
                    "VEVO channel {$channel->channelId} should have been filtered"
                );
            }
            
            // Should have exactly 5 legitimate channels
            expect($filtered)->toHaveCount(5);
        });
    });
    
    describe('Channel Ownership Validation', function () {
        
        it('validates channel ownership by name match', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Taylor Swift Official',
            );
            
            $artist = Artist::factory()->create(['name' => 'Taylor Swift']);
            
            expect($this->service->validateChannelOwnership($channel, $artist))->toBeTrue();
        });
        
        it('validates channel ownership by alias match', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Slim Shady Music',
            );
            
            $artist = Artist::factory()->create(['name' => 'Eminem']);
            ArtistAlias::create(['artist_id' => $artist->id, 'name' => 'Slim Shady']);
            
            expect($this->service->validateChannelOwnership($channel, $artist))->toBeTrue();
        });
        
        it('returns false for unrelated channel', function () {
            $channel = new YouTubeChannelDTO(
                channelId: 'UCtest123',
                subscriberCount: 1000000,
                videoCount: 100,
                subscriberCountHidden: false,
                title: 'Random Music Channel',
            );
            
            $artist = Artist::factory()->create(['name' => 'Taylor Swift']);
            
            expect($this->service->validateChannelOwnership($channel, $artist))->toBeFalse();
        });
    });
    
    describe('Caching', function () {
        
        it('caches search results for artist', function () {
            $requestCount = 0;
            
            Http::fake(function ($request) use (&$requestCount) {
                $requestCount++;
                return Http::response(['items' => []]);
            });
            
            $artist = Artist::factory()->create(['name' => 'Test Artist']);
            
            // First call
            $this->service->searchChannelsForArtist($artist);
            $firstCallCount = $requestCount;
            
            // Second call should use cache (no additional HTTP requests)
            $this->service->searchChannelsForArtist($artist);
            
            // Request count should not have increased
            expect($requestCount)->toBe($firstCallCount);
        });
        
        it('clears cache for artist', function () {
            $artist = Artist::factory()->create(['name' => 'Test Artist']);
            $cacheKey = "channel_search:{$artist->id}";
            
            Cache::put($cacheKey, ['cached_data'], 3600);
            
            $this->service->clearCacheForArtist($artist);
            
            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });
});

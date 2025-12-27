import { ref, onUnmounted, computed } from 'vue';

interface SpotifyPlayerState {
    context: {
        uri: string | null;
        metadata: any;
    } | null;
    track_window: {
        current_track: {
            id: string;
            name: string;
            album: {
                images: Array<{ url: string }>;
            };
            artists: Array<{ name: string }>;
            duration_ms: number;
        } | null;
    };
    position: number;
    paused: boolean;
    loading: boolean;
}

interface SpotifyPlayer {
    connect: () => Promise<boolean>;
    disconnect: () => void;
    getCurrentState: () => Promise<SpotifyPlayerState | null>;
    addListener: (event: string, callback: (state: any) => void) => void;
    removeListener: (event: string, callback: (state: any) => void) => void;
    togglePlay: () => Promise<void>;
    resume: () => Promise<void>;
    pause: () => Promise<void>;
    seek: (position_ms: number) => Promise<void>;
    getVolume: () => Promise<number>;
    setVolume: (volume: number) => Promise<void>;
    nextTrack: () => Promise<void>;
    previousTrack: () => Promise<void>;
    activateElement: () => Promise<void>;
}

declare global {
    interface Window {
        Spotify: {
            Player: new (options: {
                name: string;
                getOAuthToken: (callback: (token: string) => void) => void;
                volume?: number;
            }) => SpotifyPlayer;
        };
        onSpotifyWebPlaybackSDKReady: () => void;
    }
}

// SHARED GLOBAL STATE
const player = ref<SpotifyPlayer | null>(null);
const deviceId = ref<string | null>(null);
const isReady = ref(false);
const isPlaying = ref(false);
const currentTrackId = ref<string | null>(null);
const currentContextUri = ref<string | null>(null);
const currentPosition = ref(0);
const trackDuration = ref(0);
const isLoading = ref(false);
const error = ref<string | null>(null);
const accessToken = ref<string | null>(null);
let initializationPromise: Promise<void> | null = null;
let progressInterval: number | null = null;
let listenerCount = 0;

export function useSpotifyPlayback() {
    // Start progress timer
    const startProgressTimer = () => {
        stopProgressTimer();
        progressInterval = window.setInterval(() => {
            if (isPlaying.value && currentPosition.value < trackDuration.value) {
                currentPosition.value += 1000;
            } else if (currentPosition.value >= trackDuration.value) {
                stopProgressTimer();
            }
        }, 1000);
    };

    // Stop progress timer
    const stopProgressTimer = () => {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    };

    // Clear token from backend session
    const clearToken = async (): Promise<void> => {
        try {
            await fetch('/api/spotify/token', {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                },
            });
        } catch (err) {
            console.warn('Failed to clear token from backend:', err);
        }
        // Clear local token
        accessToken.value = null;
    };

    // Get access token from backend
    const fetchAccessToken = async (allowRedirect = true): Promise<string> => {
        if (accessToken.value) {
            return accessToken.value;
        }

        try {
            // Include current page URL as return_url so we can redirect back after OAuth
            const returnUrl = window.location.pathname + window.location.search;
            const tokenUrl = new URL('/api/spotify/token', window.location.origin);
            tokenUrl.searchParams.set('return_url', returnUrl);

            const response = await fetch(tokenUrl.toString(), {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                const data = await response.json();

                // If not authenticated, clear token and redirect to Spotify OAuth
                if (data.error === 'not_authenticated' && data.auth_url) {
                    await clearToken();
                    if (allowRedirect) {
                        window.location.href = data.auth_url;
                        throw new Error('Redirecting to Spotify authentication');
                    }
                    
                    const error = new Error('Spotify authentication required');
                    (error as any).authUrl = data.auth_url;
                    (error as any).needsAuth = true;
                    throw error;
                }

                throw new Error('Failed to get Spotify access token');
            }

            const data = await response.json();
            accessToken.value = data.access_token;
            return data.access_token;
        } catch (err) {
            if (err instanceof Error && err.message.includes('Redirecting')) {
                throw err; // Re-throw redirect errors
            }
            if ((err as any).needsAuth) {
                throw err;
            }
            error.value = 'Unable to authenticate with Spotify. Please try again.';
            throw err;
        }
    };

    // Check if user is authenticated without redirecting
    const checkAuthentication = async (): Promise<{ authenticated: boolean; authUrl?: string }> => {
        try {
            await fetchAccessToken(false);
            return { authenticated: true };
        } catch (err) {
            if ((err as any).needsAuth) {
                return { authenticated: false, authUrl: (err as any).authUrl };
            }
            return { authenticated: false };
        }
    };

    // Initialize Spotify Web Playback SDK
    const initializePlayer = async () => {
        // If already initialized, return
        if (player.value) {
            return;
        }

        // If initialization is in progress, wait for it
        if (initializationPromise) {
            await initializationPromise;
            return;
        }

        // Start initialization
        initializationPromise = new Promise<void>((resolve, reject) => {
            const tryCreatePlayer = async () => {
                if (player.value) {
                    resolve();
                    return;
                }
                try {
                    await createPlayer();
                    resolve();
                } catch (e) {
                    reject(e);
                }
            };

            if (window.Spotify?.Player) {
                tryCreatePlayer();
            } else {
                // Wait for SDK to load
                window.onSpotifyWebPlaybackSDKReady = tryCreatePlayer;

                // Load SDK if not already loaded
                if (!document.querySelector('script[src*="spotify-web-playback"]')) {
                    const script = document.createElement('script');
                    script.src = 'https://sdk.scdn.co/spotify-player.js';
                    script.async = true;
                    document.body.appendChild(script);
                }
            }
        }).catch(err => {
            console.error('Spotify initialization failed:', err);
            initializationPromise = null;
        });

        await initializationPromise;
    };

    const createPlayer = async () => {
        if (player.value) return;

        try {
            const spotifyPlayer = new window.Spotify.Player({
                name: 'Artist Tree Web Player',
                getOAuthToken: (cb) => {
                    // Force a re-fetch from the backend to ensure the token is not stale.
                    accessToken.value = null;
                    fetchAccessToken().then(token => cb(token));
                },
                volume: 0.5,
            });

            // Error handling
            spotifyPlayer.addListener('initialization_error', async ({ message }: { message: string }) => {
                console.error('Spotify SDK initialization error:', message);
                error.value = `Initialization error: ${message}`;
                await clearToken();
            });

            spotifyPlayer.addListener('authentication_error', async ({ message }: { message: string }) => {
                console.error('Spotify SDK authentication error:', message);
                error.value = `Authentication error: ${message}`;
                await clearToken();
            });

            spotifyPlayer.addListener('account_error', ({ message }: { message: string }) => {
                error.value = `Account error: ${message}. Please ensure you have Spotify Premium.`;
            });

            spotifyPlayer.addListener('playback_error', ({ message }: { message: string }) => {
                error.value = `Playback error: ${message}`;
            });

            // Ready state
            spotifyPlayer.addListener('ready', ({ device_id }: { device_id: string }) => {
                // Prevent duplicate ready events for the same device ID
                if (deviceId.value === device_id) return;

                deviceId.value = device_id;
                isReady.value = true;
                error.value = null;
                console.log('Spotify Player is ready with Device ID:', device_id);
            });

            // Not ready state
            spotifyPlayer.addListener('not_ready', ({ device_id }: { device_id: string }) => {
                deviceId.value = device_id;
                isReady.value = false;
            });

            // Player state changes
            spotifyPlayer.addListener('player_state_changed', (state: SpotifyPlayerState) => {
                if (!state) {
                    return;
                }

                currentPosition.value = state.position;
                isPlaying.value = !state.paused;
                isLoading.value = state.loading;

                // Manage progress timer based on playback state
                if (isPlaying.value) {
                    startProgressTimer();
                } else {
                    stopProgressTimer();
                }
                
                // Update context
                currentContextUri.value = state.context?.uri || null;

                if (state.track_window.current_track) {
                    currentTrackId.value = state.track_window.current_track.id;
                    trackDuration.value = state.track_window.current_track.duration_ms;
                } else {
                    currentTrackId.value = null;
                    trackDuration.value = 0;
                }
            });

            // Connect to player
            const connected = await spotifyPlayer.connect();
            if (!connected) {
                throw new Error('Failed to connect to Spotify player');
            }
            player.value = spotifyPlayer;
        } catch (err) {
            error.value = 'Failed to initialize Spotify player';
            console.error('Spotify player initialization error:', err);
        }
    };

    // Play a track or album
    const playTrack = async (spotifyId: string, type: 'track' | 'album' = 'track') => {
        if (!player.value || !deviceId.value) {
            await initializePlayer();
            await waitForPlayerReady();
        }

        if (!player.value || !deviceId.value) {
            error.value = 'Player not ready. Please try again.';
            return;
        }

        try {
            const token = await fetchAccessToken();

            // Transfer playback to this device
            await fetch(`https://api.spotify.com/v1/me/player`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    device_ids: [deviceId.value],
                    play: false,
                }),
            });

            // Start playback
            const body: any = {};
            if (type === 'track') {
                body.uris = [`spotify:track:${spotifyId}`];
            } else {
                body.context_uri = `spotify:album:${spotifyId}`;
            }

            await fetch(`https://api.spotify.com/v1/me/player/play?device_id=${deviceId.value}`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            
            // Set optimistic values for immediate UI feedback
            if (type === 'track') {
                currentTrackId.value = spotifyId;
            } else {
                currentContextUri.value = `spotify:album:${spotifyId}`;
            }
            isLoading.value = true;
        } catch (err) {
            error.value = 'Failed to play. Please try again.';
            console.error('Play error:', err);
        }
    };

    // Toggle play/pause
    const togglePlayPause = async () => {
        if (!player.value) {
            return;
        }

        try {
            await player.value.togglePlay();
        } catch (err) {
            error.value = 'Failed to toggle playback';
            console.error('Toggle play/pause error:', err);
        }
    };

    // Stop playback
    const stop = async () => {
        if (!player.value) {
            return;
        }

        try {
            await player.value.pause();
        } catch (err) {
            console.error('Stop error:', err);
        }
    };

    const waitForPlayerReady = (timeout = 5000) => {
        return new Promise((resolve, reject) => {
            if (isReady.value) {
                return resolve(true);
            }
            const poll = setInterval(() => {
                if (isReady.value) {
                    clearInterval(poll);
                    resolve(true);
                }
            }, 100);

            setTimeout(() => {
                clearInterval(poll);
                reject(new Error('Player initialization timed out.'));
            }, timeout);
        });
    };

    // Format position for display
    const formattedPosition = computed(() => {
        const minutes = Math.floor(currentPosition.value / 60000);
        const seconds = Math.floor((currentPosition.value % 60000) / 1000);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    });

    // Format duration for display
    const formattedDuration = computed(() => {
        const minutes = Math.floor(trackDuration.value / 60000);
        const seconds = Math.floor((trackDuration.value % 60000) / 1000);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    });

    // Progress percentage
    const progressPercentage = computed(() => {
        if (trackDuration.value === 0) {
            return 0;
        }
        return (currentPosition.value / trackDuration.value) * 100;
    });

    // Check if a specific track is currently playing
    const isTrackPlaying = (trackId: string) => {
        return currentTrackId.value === trackId && isPlaying.value;
    };
    
    // Check if a specific context (album/playlist) is currently playing
    const isContextPlaying = (contextUri: string) => {
        return currentContextUri.value === contextUri && isPlaying.value;
    };

    // TRACK LISTENERS
    listenerCount++;

    // Cleanup on unmount
    onUnmounted(() => {
        listenerCount--;
        // Only disconnect and stop timers if NO components are listening anymore
        if (listenerCount === 0) {
            stopProgressTimer();
            if (player.value && isPlaying.value) {
                player.value.disconnect();
                player.value = null;
                isReady.value = false;
                initializationPromise = null;
                deviceId.value = null;
            }
        }
    });

    return {
        isReady,
        isPlaying,
        currentTrackId,
        currentContextUri,
        currentPosition,
        trackDuration,
        isLoading,
        error,
        formattedPosition,
        formattedDuration,
        progressPercentage,
        playTrack,
        togglePlayPause,
        stop,
        isTrackPlaying,
        isContextPlaying,
        checkAuthentication,
        initializePlayer,
    };
}
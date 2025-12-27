import { ref, onUnmounted, computed } from 'vue';

interface SpotifyPlayerState {
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
    addListener: (event: string, callback: (state: SpotifyPlayerState) => void) => void;
    removeListener: (event: string, callback: (state: SpotifyPlayerState) => void) => void;
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

export function useSpotifyPlayback() {
    const player = ref<SpotifyPlayer | null>(null);
    const deviceId = ref<string | null>(null);
    const isReady = ref(false);
    const isPlaying = ref(false);
    const currentTrackId = ref<string | null>(null);
    const currentPosition = ref(0);
    const trackDuration = ref(0);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const accessToken = ref<string | null>(null);
    const isInitializing = ref(false);
    let progressInterval: number | null = null;

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
        // Prevent multiple simultaneous initialization attempts
        if (isInitializing.value) {
            return;
        }

        isInitializing.value = true;

        try {
            if (window.Spotify?.Player) {
                await createPlayer();
            } else {
                // Wait for SDK to load
                window.onSpotifyWebPlaybackSDKReady = async () => {
                    await createPlayer();
                };

                // Load SDK if not already loaded
                if (!document.querySelector('script[src*="spotify-web-playback"]')) {
                    const script = document.createElement('script');
                    script.src = 'https://sdk.scdn.co/spotify-player.js';
                    script.async = true;
                    document.body.appendChild(script);
                }
            }
        } finally {
            isInitializing.value = false;
        }
    };

    const createPlayer = async () => {
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
            spotifyPlayer.addListener('initialization_error', async ({ message }) => {
                console.error('Spotify SDK initialization error:', message);
                error.value = `Initialization error: ${message}`;

                // Clear invalid token and trigger re-authentication
                await clearToken();

                // Trigger re-authentication by fetching a new token
                try {
                    await fetchAccessToken();
                    // If successful, try to reconnect
                    if (accessToken.value) {
                        await spotifyPlayer.connect();
                    }
                } catch (err) {
                    // Re-authentication will redirect to OAuth
                    console.error('Failed to re-authenticate after initialization error:', err);
                }
            });

            spotifyPlayer.addListener('authentication_error', async ({ message }) => {
                console.error('Spotify SDK authentication error:', message);
                error.value = `Authentication error: ${message}`;

                // Clear invalid token and trigger re-authentication
                await clearToken();

                // Trigger re-authentication by fetching a new token
                try {
                    await fetchAccessToken();
                    // If successful, try to reconnect
                    if (accessToken.value) {
                        await spotifyPlayer.connect();
                    }
                } catch (err) {
                    // Re-authentication will redirect to OAuth
                    console.error('Failed to re-authenticate after auth error:', err);
                }
            });

            spotifyPlayer.addListener('account_error', ({ message }) => {
                error.value = `Account error: ${message}. Please ensure you have Spotify Premium.`;
            });

            spotifyPlayer.addListener('playback_error', ({ message }) => {
                error.value = `Playback error: ${message}`;
            });

            // Ready state
            spotifyPlayer.addListener('ready', ({ device_id }) => {
                deviceId.value = device_id;
                isReady.value = true;
                error.value = null;
            });

            // Not ready state
            spotifyPlayer.addListener('not_ready', ({ device_id }) => {
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

            // Clear invalid token if initialization fails
            // This helps when token is invalid (e.g., after email change)
            if (err instanceof Error && (
                err.message.includes('authentication') ||
                err.message.includes('token') ||
                err.message.includes('Invalid')
            )) {
                await clearToken();
                // Try to re-authenticate
                try {
                    await fetchAccessToken();
                } catch (authErr) {
                    // Will redirect to OAuth if needed
                    console.error('Failed to re-authenticate after initialization failure:', authErr);
                }
            }
        }
    };

    // Play a track
    const playTrack = async (spotifyTrackId: string) => {
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
            await fetch(`https://api.spotify.com/v1/me/player/play?device_id=${deviceId.value}`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    uris: [`spotify:track:${spotifyTrackId}`],
                }),
            });

            currentTrackId.value = spotifyTrackId;
            isLoading.value = true;
        } catch (err) {
            error.value = 'Failed to play track. Please try again.';
            console.error('Play track error:', err);
        }
    };

    // Toggle play/pause
    const togglePlayPause = async () => {
        // Initialize player if not already initialized
        if (!player.value || !deviceId.value) {
            await initializePlayer();
            await waitForPlayerReady();
        }

        if (!player.value) {
            error.value = 'Player not ready. Please try again.';
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
            currentTrackId.value = null;
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

    // Cleanup on unmount
    onUnmounted(() => {
        stopProgressTimer();
        if (player.value) {
            // Only disconnect if we are currently playing.
            // This stops the music when the user leaves the page,
            // but avoids interrupting other devices (like Desktop app) if we were idle.
            if (isPlaying.value) {
                player.value.disconnect();
            }
        }
    });

    return {
        isReady,
        isPlaying,
        currentTrackId,
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
        checkAuthentication,
    };
}


import { useStorage } from '@vueuse/core';

export interface RecentSearchArtist {
    id: number | null; // Database ID (nullable)
    spotify_id: string; // Spotify ID (always present)
    name: string;
    genres: string[];
    image_url: string | null;
    spotify_popularity: number;
    spotify_followers: number;
    score: number;
    timestamp?: number; // Optional as it's added on save
}

// Global state for recent searches to share across components if needed
const recentSearches = useStorage<RecentSearchArtist[]>(
    'artist-tree-recent-searches',
    [],
);

const MAX_RECENT_SEARCHES = 12;

export function useRecentSearches() {
    /**
     * Add an artist to recent searches
     * Removes duplicates and keeps the list limited to 12 items
     */
    const addSearch = (artist: Omit<RecentSearchArtist, 'timestamp'>) => {
        // Remove existing entry for same artist to avoid duplicates and move to top
        // Match by spotify_id as it's the most reliable unique identifier
        const existingIndex = recentSearches.value.findIndex(
            (a) => a.spotify_id === artist.spotify_id,
        );

        if (existingIndex !== -1) {
            recentSearches.value.splice(existingIndex, 1);
        }

        // Add to beginning with timestamp
        recentSearches.value.unshift({
            ...artist,
            timestamp: Date.now(),
        });

        // Limit to 12 items (covers most grid layouts)
        if (recentSearches.value.length > MAX_RECENT_SEARCHES) {
            recentSearches.value = recentSearches.value.slice(
                0,
                MAX_RECENT_SEARCHES,
            );
        }
    };

    /**
     * Clear all recent searches
     */
    const clearSearches = () => {
        recentSearches.value = [];
    };

    /**
     * Remove a specific artist from history
     */
    const removeSearch = (spotifyId: string) => {
        const index = recentSearches.value.findIndex(
            (a) => a.spotify_id === spotifyId,
        );
        if (index !== -1) {
            recentSearches.value.splice(index, 1);
        }
    };

    return {
        recentSearches,
        addSearch,
        clearSearches,
        removeSearch,
    };
}

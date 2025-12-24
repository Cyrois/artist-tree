import { ref, type Ref } from 'vue';

interface AsyncState<T> {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    load: () => Promise<void>;
}

/**
 * Composable for loading async Spotify data with loading/error states.
 *
 * @param url - API endpoint URL
 * @returns Object with data, loading, error refs and load function
 */
export function useAsyncSpotifyData<T>(url: string): AsyncState<T> {
    const data = ref<T | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const load = async () => {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(url, {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            data.value = result.data as T;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'An error occurred';
            data.value = null;
        } finally {
            loading.value = false;
        }
    };

    return {
        data: data as Ref<T | null>,
        loading,
        error,
        load,
    };
}

import { ref, type Ref } from 'vue';

interface Meta {
    limit: number;
    max_limit: number;
    has_more: boolean;
}

interface AsyncState<T> {
    data: Ref<T | null>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    meta: Ref<Meta | null>;
    load: (params?: Record<string, unknown>) => Promise<void>;
}

/**
 * Composable for loading async Spotify data with loading/error states.
 *
 * @param baseUrl - API endpoint base URL
 * @returns Object with data, loading, error, meta refs and load function
 */
export function useAsyncSpotifyData<T>(baseUrl: string): AsyncState<T> {
    const data = ref<T | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const meta = ref<Meta | null>(null);

    const load = async (params?: Record<string, unknown>) => {
        loading.value = true;
        error.value = null;

        try {
            const url = new URL(baseUrl, window.location.origin);
            if (params) {
                Object.entries(params).forEach(([key, value]) => {
                    url.searchParams.set(key, String(value));
                });
            }

            const response = await fetch(url.toString(), {
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                // Map HTTP status codes to user-friendly messages
                const statusMessages: Record<number, string> = {
                    401: 'Please log in again to continue.',
                    403: 'You do not have permission to view this content.',
                    404: 'Artist not found.',
                    429: 'Too many requests. Please wait a moment and try again.',
                    500: 'Service temporarily unavailable. Please try again later.',
                    503: 'Service temporarily unavailable. Please try again later.',
                };

                const message =
                    statusMessages[response.status] ||
                    `Unable to load data (Error ${response.status})`;
                throw new Error(message);
            }

            const result = await response.json();
            data.value = result.data as T;
            meta.value = result.meta ?? null;
        } catch (err) {
            error.value =
                err instanceof Error
                    ? err.message
                    : 'An unexpected error occurred. Please try again.';
            data.value = null;
        } finally {
            loading.value = false;
        }
    };

    return {
        data: data as Ref<T | null>,
        loading,
        error,
        meta: meta as Ref<Meta | null>,
        load,
    };
}

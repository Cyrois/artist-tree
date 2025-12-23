import { loadLanguageAsync, trans, wTrans } from 'laravel-vue-i18n';
import { onMounted, ref } from 'vue';

type Locale = 'en'; // Extend with 'es' | 'fr' | etc. when adding languages

const locale = ref<Locale>('en');

export function useLocale() {
    onMounted(() => {
        if (typeof window === 'undefined') {
            return;
        }

        // Load locale from localStorage on mount (client-side only)
        const savedLocale = localStorage.getItem('locale') as Locale | null;

        if (savedLocale) {
            locale.value = savedLocale;
        }
    });

    async function setLocale(newLocale: Locale) {
        if (typeof window === 'undefined') {
            return;
        }

        locale.value = newLocale;
        localStorage.setItem('locale', newLocale);

        // Dynamically load language files
        await loadLanguageAsync(newLocale);
    }

    return {
        locale,
        setLocale,
        trans, // Alias for convenience
        wTrans, // Alias for convenience (with pluralization support)
    };
}

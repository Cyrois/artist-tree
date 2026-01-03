import { AppPageProps } from '@/types/index';
import { Page, Router } from '@inertiajs/core';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
    }
}

declare module '@inertiajs/core' {
    type PageProps = AppPageProps;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: Router;
        $page: Page<AppPageProps>;
    }
}

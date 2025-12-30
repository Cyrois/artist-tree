import 'vue';

declare module 'vue' {
    interface ComponentCustomProperties {
        $t: (
            key: string,
            replacements?: Record<string, string | number>,
        ) => string;
        trans: (
            key: string,
            replacements?: Record<string, string | number>,
        ) => string;
        transChoice: (
            key: string,
            number: number,
            replacements?: Record<string, string | number>,
        ) => string;
    }
}

export {};

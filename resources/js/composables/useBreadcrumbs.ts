import { trans } from 'laravel-vue-i18n';

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export function useBreadcrumbs() {
    const dashboard = (): BreadcrumbItem[] => [
        { title: trans('common.breadcrumb_dashboard'), href: '/dashboard' }
    ];

    const search = (): BreadcrumbItem[] => [
        { title: trans('common.breadcrumb_search_artists'), href: '/search' }
    ];

    const artist = (name: string, id: number | string): BreadcrumbItem[] => [
        ...search(),
        { title: name, href: `/artist/${id}` }
    ];

    const lineups = (): BreadcrumbItem[] => [
        { title: trans('common.breadcrumb_my_lineups'), href: '/lineups' }
    ];

    const lineup = (name: string, id: number | string): BreadcrumbItem[] => [
        ...lineups(),
        { title: name, href: `/lineups/${id}` }
    ];

    return {
        dashboard,
        search,
        artist,
        lineups,
        lineup
    };
}

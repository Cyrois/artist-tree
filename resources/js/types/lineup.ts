import type { Artist } from '@/data/types';

export interface StackGroup {
    id: string;
    primary: Artist;
    alternatives: Artist[];
}

export type GroupedArtist =
    | { type: 'independent'; artist: Artist }
    | { type: 'stack'; stack: StackGroup };

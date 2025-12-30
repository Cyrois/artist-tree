// Artist Types
export interface Artist {
    id: number;
    name: string;
    genre: string[];
    score: number;
    spotifyListeners: number;
    spotifyPopularity: number;
    spotifyFollowers: number;
    youtubeSubscribers: number;
    youtubeViews: number;
    instagramFollowers: number;
    twitterFollowers: number;
    lastUpdated: string;
    country: string;
    formedYear: number;
    bio: string;
    topTracks: string[];
    albums: string[];
    metricsHistory: {
        listeners: number[];
        months: string[];
    };
    tierSuggestion: TierType;
    similarArtists: number[];
    image?: string;
}

// Tier Types
export type TierType = 'headliner' | 'sub_headliner' | 'mid_tier' | 'undercard';

export interface TierConfig {
    label: string;
    color: string;
    bgColor: string;
}

// Booking Status Types
export type BookingStatus =
    | 'idea'
    | 'outreach'
    | 'negotiating'
    | 'contract_sent'
    | 'contract_signed'
    | 'confirmed'
    | 'declined';

export interface StatusConfig {
    label: string;
    color: string;
    bgColor: string;
    icon: string;
    description: string;
}

export interface ArtistStatus {
    status: BookingStatus;
    fee: number | null;
    notes: string;
    contactEmail: string;
}

// Lineup Types
export interface Lineup {
    id: number;
    name: string;
    description: string;
    updatedAt: string;
    createdAt: string;
    artists: Record<TierType, number[]> | any[]; // Allow array from API
    artistStatuses?: Record<number, ArtistStatus>; // Optional now
    stats?: {
        artistCount: number;
        avgScore: number;
        confirmedCount: number;
        pendingCount: number;
        totalBudget: number;
    };
    previewArtists?: {
        id: number;
        name: string;
        image: string | null;
    }[];
}

// Schedule Types
export interface ScheduleSlot {
    day: string;
    stage: string;
    startTime: string;
    duration: number;
}

// Metric Weight Types
export interface MetricWeight {
    key: string;
    label: string;
    value: number;
    icon: string;
}

// Team Member Types
export interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    avatar?: string;
}

// Lineup Stats (computed)
export interface LineupStats {
    artistCount: number;
    avgScore: number;
    totalBudget: number;
    confirmedBudget: number;
    ideaCount: number;
    outreachCount: number;
    negotiatingCount: number;
    contractSentCount: number;
    contractSignedCount: number;
    confirmedCount: number;
    declinedCount: number;
}

import type {
    BookingStatus,
    StatusConfig,
    TierConfig,
    TierType,
} from './types';

// Color palette (mapped to CSS variables where possible)
export const colors = {
    primary: '#1a1a1a',
    secondary: '#6b7280',
    accent: '#e85d4c',
    background: '#ffffff',
    surface: '#f8f8f8',
    border: '#e5e5e5',
};

// Avatar colors for placeholder images
export const avatarColors = [
    '#e85d4c',
    '#6366f1',
    '#10b981',
    '#8b5cf6',
    '#f59e0b',
    '#ef4444',
    '#ec4899',
    '#3b82f6',
    '#84cc16',
    '#14b8a6',
    '#64748b',
    '#0ea5e9',
    '#dc2626',
    '#d946ef',
    '#7c3aed',
    '#059669',
    '#eab308',
];

// Tier configuration
export const tierConfig: Record<TierType, TierConfig> = {
    headliner: { label: 'HEADLINER', color: '#1a1a1a', bgColor: '#f0f0f0' },
    sub_headliner: {
        label: 'SUB-HEADLINER',
        color: '#404040',
        bgColor: '#f5f5f5',
    },
    mid_tier: { label: 'MID-TIER', color: '#666666', bgColor: '#f8f8f8' },
    undercard: { label: 'UNDERCARD', color: '#888888', bgColor: '#fafafa' },
};

// Tier order for iteration
export const tierOrder: TierType[] = [
    'headliner',
    'sub_headliner',
    'mid_tier',
    'undercard',
];

// Booking status configuration
export const statusConfig: Record<BookingStatus, StatusConfig> = {
    idea: {
        label: 'Idea',
        color: '#8b5cf6',
        bgColor: '#f3e8ff',
        icon: 'Lightbulb',
        description: 'Potential artist to consider',
    },
    outreach: {
        label: 'Outreach',
        color: '#3b82f6',
        bgColor: '#dbeafe',
        icon: 'Mail',
        description: 'Initial contact in progress',
    },
    negotiating: {
        label: 'Negotiating',
        color: '#f59e0b',
        bgColor: '#fef3c7',
        icon: 'DollarSign',
        description: 'Discussing terms and fees',
    },
    contract_sent: {
        label: 'Contract Sent',
        color: '#6366f1',
        bgColor: '#e0e7ff',
        icon: 'Send',
        description: 'Awaiting signature',
    },
    contract_signed: {
        label: 'Signed',
        color: '#10b981',
        bgColor: '#d1fae5',
        icon: 'FileSignature',
        description: 'Contract signed, pending deposit',
    },
    confirmed: {
        label: 'Confirmed',
        color: '#059669',
        bgColor: '#a7f3d0',
        icon: 'CheckCircle',
        description: 'Fully confirmed and booked',
    },
    declined: {
        label: 'Declined',
        color: '#ef4444',
        bgColor: '#fee2e2',
        icon: 'X',
        description: 'Artist declined or unavailable',
    },
};

// Booking status order for Kanban
export const statusOrder: BookingStatus[] = [
    'idea',
    'outreach',
    'negotiating',
    'contract_sent',
    'contract_signed',
    'confirmed',
    'declined',
];

// All available genres
export const allGenres = [
    'Hip-Hop',
    'Rap',
    'Pop',
    'Alternative',
    'Electronic',
    'House',
    'Techno',
    'Indie',
    'Rock',
    'Psychedelic',
    'Bedroom Pop',
    'UK Garage',
    'Hyperpop',
    'Ambient',
    'Jazz',
    'R&B',
    'Neo-Soul',
    'Reggaeton',
    'Latin Trap',
    'Dance',
    'Disco',
    'Trap',
    'Synth-Pop',
    'Dream Pop',
    'Shoegaze',
    'Electropop',
    'West Coast',
    'UK Dance',
    'K-House',
    'Folktronica',
];

// Metric weight presets
export const metricPresets = {
    balanced: {
        label: 'Balanced',
        weights: {
            spotifyListeners: 0.4,
            spotifyPopularity: 0.3,
            youtubeSubscribers: 0.3,
        },
    },
    streaming_focused: {
        label: 'Streaming Focused',
        weights: {
            spotifyListeners: 0.55,
            spotifyPopularity: 0.3,
            youtubeSubscribers: 0.15,
        },
    },
    social_media_focused: {
        label: 'Social Media Focused',
        weights: {
            spotifyListeners: 0.2,
            spotifyPopularity: 0.15,
            youtubeSubscribers: 0.65,
        },
    },
};

// Schedule configuration
export const scheduleDays = ['Friday', 'Saturday', 'Sunday'];
export const scheduleStages = [
    'Main Stage',
    'Second Stage',
    'Tent Stage',
    'DJ Booth',
];

// Score color ranges (for ScoreBadge component)
export function getScoreColor(score: number): { bg: string; text: string } {
    if (score >= 85)
        return {
            bg: 'hsl(var(--score-high-bg))',
            text: 'hsl(var(--score-high))',
        };
    if (score >= 70)
        return {
            bg: 'hsl(var(--score-medium-bg))',
            text: 'hsl(var(--score-medium))',
        };
    if (score >= 55)
        return {
            bg: 'hsl(var(--score-low-bg))',
            text: 'hsl(var(--score-low))',
        };
    return {
        bg: 'hsl(var(--score-critical-bg))',
        text: 'hsl(var(--score-critical))',
    };
}

// Helper to get initials from name
export function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
}

// Helper to generate avatar URL with initials
export function getAvatarUrl(name: string, index: number): string {
    const initials = getInitials(name);
    const bgColor = avatarColors[index % avatarColors.length];
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="${bgColor}"/><text x="200" y="200" font-family="Arial,sans-serif" font-size="140" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text></svg>`;
    return `data:image/svg+xml,${encodeURIComponent(svg)}`;
}

// Format large numbers (1M, 1.2B, etc.)
export function formatNumber(num: number): string {
    if (num >= 1000000000) {
        return (num / 1000000000).toFixed(1).replace(/\.0$/, '') + 'B';
    }
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return num.toString();
}

// Format currency
export function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

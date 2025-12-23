import type { Lineup, LineupStats, TierType, ScheduleSlot } from './types';
import { getArtistById, getArtistsByIds } from './artists';
import { tierOrder } from './constants';

// Mock lineup data
export const lineups: Lineup[] = [
  {
    id: 1,
    name: 'Summer Fest 2025',
    description: 'Annual summer music festival featuring diverse genres',
    updatedAt: '2 hours ago',
    createdAt: 'Dec 15, 2025',
    artists: {
      headliner: [1, 2, 13, 14, 15, 16, 17],
      sub_headliner: [9, 10],
      mid_tier: [3, 4, 7, 8],
      undercard: [5, 6, 11, 12],
    },
    artistStatuses: {
      1: { status: 'confirmed', fee: 500000, notes: 'Headline act Saturday', contactEmail: 'booking@kendricklamar.com' },
      2: { status: 'contract_signed', fee: 450000, notes: 'Deposit received', contactEmail: 'mgmt@billieeilish.com' },
      13: {
        status: 'negotiating',
        fee: 600000,
        notes: 'Wants top billing Sunday night',
        contactEmail: 'xo@theweeknd.com',
      },
      14: { status: 'idea', fee: null, notes: 'Would be amazing Friday headliner', contactEmail: '' },
      15: {
        status: 'outreach',
        fee: null,
        notes: 'Reached out via agent 12/19',
        contactEmail: 'cactus@travisscott.com',
      },
      16: { status: 'contract_sent', fee: 420000, notes: 'DocuSign sent 12/21, waiting', contactEmail: 'tde@sza.com' },
      17: { status: 'idea', fee: null, notes: 'Latin representation - checking availability', contactEmail: '' },
      9: { status: 'contract_sent', fee: 300000, notes: 'Sent via DocuSign 12/20', contactEmail: 'tyler@golfwang.com' },
      10: {
        status: 'negotiating',
        fee: null,
        notes: 'Discussing fee, asking 350k',
        contactEmail: 'booking@charlixcx.com',
      },
      3: {
        status: 'confirmed',
        fee: 150000,
        notes: 'Special production requirements',
        contactEmail: 'fred@fredagain.com',
      },
      4: { status: 'contract_sent', fee: 120000, notes: 'Awaiting signature', contactEmail: 'mgmt@tameimpala.com' },
      7: { status: 'outreach', fee: null, notes: 'Initial email sent 12/18', contactEmail: 'bea@dirtyhit.com' },
      8: { status: 'confirmed', fee: 100000, notes: '', contactEmail: 'disclosure@island.com' },
      5: { status: 'idea', fee: null, notes: 'Would be great for Sunday afternoon', contactEmail: '' },
      6: {
        status: 'negotiating',
        fee: null,
        notes: 'Agent wants 80k, we offered 60k',
        contactEmail: 'peggy@xlrecordings.com',
      },
      11: { status: 'outreach', fee: null, notes: 'Waiting for response', contactEmail: 'kieran@textrecords.com' },
      12: { status: 'declined', fee: null, notes: 'Not available during festival dates', contactEmail: 'sam@ninjatune.com' },
    },
  },
  {
    id: 2,
    name: 'Desert Dreams',
    description: 'Electronic and indie focused desert festival',
    updatedAt: '1 day ago',
    createdAt: 'Dec 10, 2025',
    artists: {
      headliner: [10],
      sub_headliner: [3, 4],
      mid_tier: [6, 8, 11],
      undercard: [12],
    },
    artistStatuses: {
      10: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      3: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      4: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      6: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      8: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      11: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      12: { status: 'idea', fee: null, notes: '', contactEmail: '' },
    },
  },
  {
    id: 3,
    name: 'Warehouse Series',
    description: 'Underground electronic music showcase',
    updatedAt: '3 days ago',
    createdAt: 'Dec 1, 2025',
    artists: {
      headliner: [3],
      sub_headliner: [8],
      mid_tier: [6, 11],
      undercard: [12],
    },
    artistStatuses: {
      3: { status: 'confirmed', fee: 75000, notes: '', contactEmail: 'fred@fredagain.com' },
      8: { status: 'confirmed', fee: 50000, notes: '', contactEmail: 'disclosure@island.com' },
      6: { status: 'contract_sent', fee: 35000, notes: '', contactEmail: 'peggy@xlrecordings.com' },
      11: { status: 'outreach', fee: null, notes: '', contactEmail: '' },
      12: { status: 'idea', fee: null, notes: '', contactEmail: '' },
    },
  },
];

// Mock schedule data (for Summer Fest 2025)
export const mockSchedule: Record<number, ScheduleSlot> = {
  1: { day: 'Saturday', stage: 'Main Stage', startTime: '21:00', duration: 90 },
  2: { day: 'Friday', stage: 'Main Stage', startTime: '21:00', duration: 90 },
  13: { day: 'Sunday', stage: 'Main Stage', startTime: '21:00', duration: 90 },
  3: { day: 'Friday', stage: 'Second Stage', startTime: '19:00', duration: 75 },
  4: { day: 'Saturday', stage: 'Second Stage', startTime: '18:00', duration: 75 },
  8: { day: 'Saturday', stage: 'Tent Stage', startTime: '16:00', duration: 60 },
  6: { day: 'Friday', stage: 'DJ Booth', startTime: '23:00', duration: 120 },
  11: { day: 'Saturday', stage: 'Tent Stage', startTime: '14:00', duration: 60 },
};

// Helper functions for data access
export function getLineups(): Lineup[] {
  return lineups;
}

export function getLineupById(id: number): Lineup | undefined {
  return lineups.find((l) => l.id === id);
}

export function getLineupStats(lineup: Lineup): LineupStats {
  const allArtistIds = tierOrder.flatMap((tier) => lineup.artists[tier]);
  const artists = getArtistsByIds(allArtistIds);

  const confirmedStatuses = ['confirmed', 'contract_signed'];
  const pendingStatuses = ['outreach', 'negotiating', 'contract_sent'];

  let confirmedCount = 0;
  let pendingCount = 0;
  let declinedCount = 0;
  let totalBudget = 0;

  allArtistIds.forEach((artistId) => {
    const status = lineup.artistStatuses[artistId];
    if (status) {
      if (confirmedStatuses.includes(status.status)) confirmedCount++;
      if (pendingStatuses.includes(status.status)) pendingCount++;
      if (status.status === 'declined') declinedCount++;
      if (status.fee) totalBudget += status.fee;
    }
  });

  const avgScore = artists.length > 0 ? Math.round(artists.reduce((sum, a) => sum + a.score, 0) / artists.length) : 0;

  return {
    artistCount: allArtistIds.length,
    avgScore,
    confirmedCount,
    pendingCount,
    declinedCount,
    totalBudget,
  };
}

export function getLineupArtistsByTier(lineup: Lineup, tier: TierType) {
  return getArtistsByIds(lineup.artists[tier]);
}

export function getAllLineupArtists(lineup: Lineup) {
  const allArtistIds = tierOrder.flatMap((tier) => lineup.artists[tier]);
  return getArtistsByIds(allArtistIds);
}

export function getLineupSchedule(lineupId: number): Record<number, ScheduleSlot> {
  // For now, only return schedule for Summer Fest
  if (lineupId === 1) {
    return mockSchedule;
  }
  return {};
}

export function getArtistsByBookingStatus(lineup: Lineup, status: string) {
  const artistIds = Object.entries(lineup.artistStatuses)
    .filter(([_, s]) => s.status === status)
    .map(([id]) => parseInt(id));
  return getArtistsByIds(artistIds);
}

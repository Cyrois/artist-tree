import React, { useState } from 'react';
import { Search, Plus, Music, Users, Settings, ChevronDown, GripVertical, Sparkles, TrendingUp, Youtube, RefreshCw, X, Check, Home, List, Building2, SlidersHorizontal, ExternalLink, Clock, ChevronRight, ChevronLeft, ArrowUp, ArrowDown, Calendar, Globe, Instagram, Twitter, BarChart3, PieChart, Target, Zap, Award, Info, Lightbulb, FileText, Send, CheckCircle, AlertCircle, MoreHorizontal, Mail, Phone, DollarSign, FileSignature, Layers, Link2, Unlink } from 'lucide-react';

// Color palette
const colors = {
  primary: '#1a1a1a',
  secondary: '#6b7280',
  accent: '#e85d4c',
  background: '#ffffff',
  surface: '#f8f8f8',
  border: '#e5e5e5',
};

// Avatar colors for placeholder images
const avatarColors = ['#e85d4c', '#6366f1', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#ec4899', '#3b82f6', '#84cc16', '#14b8a6', '#64748b', '#0ea5e9', '#dc2626', '#d946ef', '#7c3aed', '#059669', '#eab308'];

// Generate placeholder avatar with initials
const getAvatarUrl = (name, index) => {
  const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
  const bgColor = avatarColors[index % avatarColors.length];
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="${bgColor}"/><text x="200" y="200" font-family="Arial,sans-serif" font-size="140" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text></svg>`;
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
};

// Artist booking status configuration
const statusConfig = {
  idea: { 
    label: 'Idea', 
    color: '#8b5cf6', 
    bgColor: '#f3e8ff',
    icon: Lightbulb,
    description: 'Potential artist to consider'
  },
  outreach: { 
    label: 'Outreach', 
    color: '#3b82f6', 
    bgColor: '#dbeafe',
    icon: Mail,
    description: 'Initial contact in progress'
  },
  negotiating: { 
    label: 'Negotiating', 
    color: '#f59e0b', 
    bgColor: '#fef3c7',
    icon: DollarSign,
    description: 'Discussing terms and fees'
  },
  contract_sent: { 
    label: 'Contract Sent', 
    color: '#6366f1', 
    bgColor: '#e0e7ff',
    icon: Send,
    description: 'Awaiting signature'
  },
  contract_signed: { 
    label: 'Signed', 
    color: '#10b981', 
    bgColor: '#d1fae5',
    icon: FileSignature,
    description: 'Contract signed, pending deposit'
  },
  confirmed: { 
    label: 'Confirmed', 
    color: '#059669', 
    bgColor: '#a7f3d0',
    icon: CheckCircle,
    description: 'Fully confirmed and booked'
  },
  declined: { 
    label: 'Declined', 
    color: '#ef4444', 
    bgColor: '#fee2e2',
    icon: X,
    description: 'Artist declined or unavailable'
  },
};

// Extended mock data with more details
const mockArtistsData = [
  { 
    id: 1, 
    name: 'Kendrick Lamar', 
    genre: ['Hip-Hop', 'Rap', 'West Coast'], 
    score: 94, 
    spotifyListeners: 58200000, 
    spotifyPopularity: 92, 
    spotifyFollowers: 32100000,
    youtubeSubscribers: 18400000,
    youtubeViews: 8200000000,
    instagramFollowers: 17800000,
    twitterFollowers: 13200000,
    lastUpdated: '2 hours ago',
    country: 'United States',
    formedYear: 2004,
    bio: 'Kendrick Lamar is a critically acclaimed rapper and songwriter from Compton, California. Known for his complex lyrics and storytelling.',
    topTracks: ['Not Like Us', 'HUMBLE.', 'All The Stars', 'DNA.', 'Money Trees'],
    albums: ['GNX (2024)', 'Mr. Morale & The Big Steppers (2022)', 'DAMN. (2017)', 'untitled unmastered. (2016)', 'To Pimp a Butterfly (2015)', 'good kid, m.A.A.d city (2012)', 'Section.80 (2011)'],
    metricsHistory: {
      listeners: [45000000, 48000000, 52000000, 55000000, 58200000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [9, 2, 10]
  },
  { 
    id: 2, 
    name: 'Billie Eilish', 
    genre: ['Pop', 'Alternative', 'Electropop'], 
    score: 91, 
    spotifyListeners: 87500000, 
    spotifyPopularity: 95, 
    spotifyFollowers: 61200000,
    youtubeSubscribers: 51200000,
    youtubeViews: 22400000000,
    instagramFollowers: 110000000,
    twitterFollowers: 6800000,
    lastUpdated: '1 hour ago',
    country: 'United States',
    formedYear: 2015,
    bio: 'Billie Eilish is an American singer-songwriter known for her distinctive voice and genre-bending music style.',
    topTracks: ['Birds of a Feather', 'bad guy', 'Lovely', 'Ocean Eyes', 'Therefore I Am'],
    albums: ['Hit Me Hard and Soft (2024)', 'Happier Than Ever (2021)', 'When We All Fall Asleep, Where Do We Go? (2019)', 'dont smile at me EP (2017)'],
    metricsHistory: {
      listeners: [82000000, 84000000, 85000000, 86500000, 87500000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [10, 7, 1]
  },
  { 
    id: 3, 
    name: 'Fred Again..', 
    genre: ['Electronic', 'House', 'UK Dance'], 
    score: 82, 
    spotifyListeners: 18700000, 
    spotifyPopularity: 78, 
    spotifyFollowers: 4200000,
    youtubeSubscribers: 890000,
    youtubeViews: 245000000,
    instagramFollowers: 1200000,
    twitterFollowers: 380000,
    lastUpdated: '3 hours ago',
    country: 'United Kingdom',
    formedYear: 2019,
    bio: 'Fred Again.. is a British DJ and producer known for his emotive electronic music and innovative live performances.',
    topTracks: ['Leavemealone', 'Delilah (pull me out of this)', 'Marea', 'Turn On The Lights again..', 'Places to Be'],
    albums: ['ten days (2024)', 'USB (2023)', 'Actual Life 3 (2022)', 'Actual Life 2 (2021)', 'Actual Life (2021)'],
    metricsHistory: {
      listeners: [12000000, 14000000, 15500000, 17000000, 18700000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'sub_headliner',
    similarArtists: [8, 6, 11]
  },
  { 
    id: 4, 
    name: 'Tame Impala', 
    genre: ['Psychedelic', 'Indie', 'Synth-Pop'], 
    score: 79, 
    spotifyListeners: 24100000, 
    spotifyPopularity: 81,
    spotifyFollowers: 10800000,
    youtubeSubscribers: 2100000,
    youtubeViews: 890000000,
    instagramFollowers: 2400000,
    twitterFollowers: 520000,
    lastUpdated: '5 hours ago',
    country: 'Australia',
    formedYear: 2007,
    bio: 'Tame Impala is a psychedelic music project led by Kevin Parker, blending rock, electronic, and pop influences.',
    topTracks: ['The Less I Know the Better', 'Let It Happen', 'Feels Like We Only Go Backwards', 'Borderline', 'Lost in Yesterday'],
    albums: ['The Slow Rush (2020)', 'Currents (2015)', 'Lonerism (2012)', 'Innerspeaker (2010)'],
    metricsHistory: {
      listeners: [22000000, 22800000, 23200000, 23800000, 24100000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'sub_headliner',
    similarArtists: [7, 11, 5]
  },
  { 
    id: 5, 
    name: 'Japanese Breakfast', 
    genre: ['Indie Pop', 'Rock', 'Dream Pop'], 
    score: 65, 
    spotifyListeners: 3200000, 
    spotifyPopularity: 62,
    spotifyFollowers: 980000,
    youtubeSubscribers: 340000,
    youtubeViews: 78000000,
    instagramFollowers: 420000,
    twitterFollowers: 180000,
    lastUpdated: '1 day ago',
    country: 'United States',
    formedYear: 2013,
    bio: 'Japanese Breakfast is the solo project of Michelle Zauner, known for dreamy indie pop and her memoir "Crying in H Mart".',
    topTracks: ['Be Sweet', 'Kokomo, IN', 'Everybody Wants to Love You', 'Savage Good Boy', 'Paprika'],
    albums: ['Jubilee (2021)', 'Soft Sounds from Another Planet (2017)', 'Psychopomp (2016)'],
    metricsHistory: {
      listeners: [2800000, 2900000, 3000000, 3100000, 3200000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'mid_tier',
    similarArtists: [7, 4, 12]
  },
  { 
    id: 6, 
    name: 'Peggy Gou', 
    genre: ['House', 'Techno', 'K-House'], 
    score: 61, 
    spotifyListeners: 4100000, 
    spotifyPopularity: 58,
    spotifyFollowers: 890000,
    youtubeSubscribers: 210000,
    youtubeViews: 62000000,
    instagramFollowers: 1800000,
    twitterFollowers: 95000,
    lastUpdated: '12 hours ago',
    country: 'South Korea',
    formedYear: 2016,
    bio: 'Peggy Gou is a South Korean DJ and producer based in Berlin, known for her distinctive blend of house and techno.',
    topTracks: ['(It Goes Like) Nanana', 'Starry Night', 'I Go', 'Lobster Telephone', 'Han Jan'],
    albums: ['I Hear You (2024)', 'Once EP (2018)', 'Art of War Part 1 EP (2016)'],
    metricsHistory: {
      listeners: [2800000, 3200000, 3500000, 3800000, 4100000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'mid_tier',
    similarArtists: [3, 8, 11]
  },
  { 
    id: 7, 
    name: 'beabadoobee', 
    genre: ['Indie Rock', 'Bedroom Pop', 'Shoegaze'], 
    score: 72, 
    spotifyListeners: 16800000, 
    spotifyPopularity: 75,
    spotifyFollowers: 5200000,
    youtubeSubscribers: 1200000,
    youtubeViews: 380000000,
    instagramFollowers: 3100000,
    twitterFollowers: 620000,
    lastUpdated: '4 hours ago',
    country: 'United Kingdom',
    formedYear: 2017,
    bio: 'beabadoobee is a Filipino-British singer-songwriter known for her nostalgic indie rock sound and candid lyrics.',
    topTracks: ['Glue Song', 'Coffee', 'the perfect pair', 'Talk', 'Care'],
    albums: ['This Is How Tomorrow Moves (2024)', 'Beatopia (2022)', 'Fake It Flowers (2020)', 'Loveworm EP (2019)', 'Patched Up EP (2018)'],
    metricsHistory: {
      listeners: [12000000, 13500000, 14800000, 15900000, 16800000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'sub_headliner',
    similarArtists: [5, 2, 4]
  },
  { 
    id: 8, 
    name: 'Disclosure', 
    genre: ['House', 'UK Garage', 'Electronic'], 
    score: 74, 
    spotifyListeners: 9400000, 
    spotifyPopularity: 71,
    spotifyFollowers: 3200000,
    youtubeSubscribers: 1800000,
    youtubeViews: 1200000000,
    instagramFollowers: 890000,
    twitterFollowers: 450000,
    lastUpdated: '6 hours ago',
    country: 'United Kingdom',
    formedYear: 2010,
    bio: 'Disclosure is an English electronic music duo consisting of brothers Guy and Howard Lawrence.',
    topTracks: ['Latch', 'You & Me', 'White Noise', 'When a Fire Starts to Burn', 'Omen'],
    albums: ['Alchemy (2024)', 'Energy (2020)', 'Caracal (2015)', 'Settle (2013)'],
    metricsHistory: {
      listeners: [8200000, 8600000, 8900000, 9200000, 9400000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'sub_headliner',
    similarArtists: [3, 6, 11]
  },
  { 
    id: 9, 
    name: 'Tyler, the Creator', 
    genre: ['Hip-Hop', 'Alternative', 'Neo-Soul'], 
    score: 89, 
    spotifyListeners: 42300000, 
    spotifyPopularity: 88,
    spotifyFollowers: 22400000,
    youtubeSubscribers: 8900000,
    youtubeViews: 3100000000,
    instagramFollowers: 18200000,
    twitterFollowers: 11500000,
    lastUpdated: '30 mins ago',
    country: 'United States',
    formedYear: 2007,
    bio: 'Tyler, the Creator is an American rapper, singer, and record producer known for his creative vision and genre-defying music.',
    topTracks: ['See You Again', 'EARFQUAKE', 'WUSYANAME', 'NEW MAGIC WAND', 'LUMBERJACK'],
    albums: ['CHROMAKOPIA (2024)', 'Call Me If You Get Lost (2021)', 'IGOR (2019)', 'Flower Boy (2017)', 'Cherry Bomb (2015)', 'Wolf (2013)', 'Goblin (2011)'],
    metricsHistory: {
      listeners: [35000000, 37000000, 39000000, 41000000, 42300000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [1, 10, 2]
  },
  { 
    id: 10, 
    name: 'Charli XCX', 
    genre: ['Pop', 'Hyperpop', 'Dance'], 
    score: 85, 
    spotifyListeners: 38100000, 
    spotifyPopularity: 86,
    spotifyFollowers: 12800000,
    youtubeSubscribers: 5200000,
    youtubeViews: 2800000000,
    instagramFollowers: 5400000,
    twitterFollowers: 4200000,
    lastUpdated: '1 hour ago',
    country: 'United Kingdom',
    formedYear: 2008,
    bio: 'Charli XCX is a British singer and songwriter at the forefront of the hyperpop movement.',
    topTracks: ['360', 'Apple', 'Speed Drive', 'Boom Clap', 'I Love It'],
    albums: ['BRAT (2024)', 'Crash (2022)', 'how i\'m feeling now (2020)', 'Charli (2019)', 'Pop 2 (2017)', 'Number 1 Angel (2017)', 'Sucker (2014)', 'True Romance (2013)'],
    metricsHistory: {
      listeners: [28000000, 31000000, 34000000, 36500000, 38100000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [2, 7, 9]
  },
  { 
    id: 11, 
    name: 'Four Tet', 
    genre: ['Electronic', 'Ambient', 'Folktronica'], 
    score: 68, 
    spotifyListeners: 5600000, 
    spotifyPopularity: 64,
    spotifyFollowers: 1100000,
    youtubeSubscribers: 180000,
    youtubeViews: 89000000,
    instagramFollowers: 320000,
    twitterFollowers: 280000,
    lastUpdated: '2 days ago',
    country: 'United Kingdom',
    formedYear: 1997,
    bio: 'Four Tet is the stage name of Kieran Hebden, an English musician known for his textural, sample-based electronic music.',
    topTracks: ['Baby', 'Loved', 'Only Human', 'Planet', 'Parallel'],
    albums: ['Three (2024)', 'Sixteen Oceans (2020)', 'New Energy (2017)', 'Beautiful Rewind (2013)', 'Pink (2012)', 'There Is Love in You (2010)', 'Ringer (2008)', 'Everything Ecstatic (2005)'],
    metricsHistory: {
      listeners: [4800000, 5000000, 5200000, 5400000, 5600000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'mid_tier',
    similarArtists: [12, 3, 8]
  },
  { 
    id: 12, 
    name: 'Floating Points', 
    genre: ['Electronic', 'Jazz', 'Ambient'], 
    score: 58, 
    spotifyListeners: 1800000, 
    spotifyPopularity: 52,
    spotifyFollowers: 420000,
    youtubeSubscribers: 95000,
    youtubeViews: 32000000,
    instagramFollowers: 180000,
    twitterFollowers: 85000,
    lastUpdated: '1 day ago',
    country: 'United Kingdom',
    formedYear: 2008,
    bio: 'Floating Points is the stage name of Sam Shepherd, a British electronic musician and neuroscientist.',
    topTracks: ['Silhouettes (I, II & III)', 'Last Bloom', 'Ratio', 'Anasickmodular', 'Birth'],
    albums: ['Cascade (2024)', 'Promises (2021)', 'Crush (2019)', 'Elaenia (2015)'],
    metricsHistory: {
      listeners: [1500000, 1580000, 1650000, 1720000, 1800000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'undercard',
    similarArtists: [11, 3, 6]
  },
  { 
    id: 13, 
    name: 'The Weeknd', 
    genre: ['R&B', 'Pop', 'Synth-pop'], 
    score: 96, 
    spotifyListeners: 94500000, 
    spotifyPopularity: 96,
    spotifyFollowers: 52000000,
    youtubeSubscribers: 35200000,
    youtubeViews: 18500000000,
    instagramFollowers: 72000000,
    twitterFollowers: 20500000,
    lastUpdated: '1 hour ago',
    country: 'Canada',
    formedYear: 2009,
    bio: 'The Weeknd is a Canadian singer and songwriter known for his distinctive falsetto and dark, brooding R&B sound.',
    topTracks: ['Blinding Lights', 'Starboy', 'Save Your Tears', 'Die For You', 'The Hills'],
    albums: ['Hurry Up Tomorrow (2025)', 'Dawn FM (2022)', 'After Hours (2020)', 'Starboy (2016)', 'Beauty Behind the Madness (2015)', 'Kiss Land (2013)', 'Trilogy (2012)'],
    metricsHistory: {
      listeners: [88000000, 90000000, 91500000, 93000000, 94500000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [1, 2, 14]
  },
  { 
    id: 14, 
    name: 'Dua Lipa', 
    genre: ['Pop', 'Dance', 'Disco'], 
    score: 93, 
    spotifyListeners: 87200000, 
    spotifyPopularity: 94,
    spotifyFollowers: 45000000,
    youtubeSubscribers: 21800000,
    youtubeViews: 12400000000,
    instagramFollowers: 88000000,
    twitterFollowers: 8900000,
    lastUpdated: '3 hours ago',
    country: 'United Kingdom',
    formedYear: 2015,
    bio: 'Dua Lipa is a British-Albanian singer and songwriter known for her disco-influenced pop and powerful vocals.',
    topTracks: ['Levitating', 'Don\'t Start Now', 'New Rules', 'Physical', 'One Kiss'],
    albums: ['Radical Optimism (2024)', 'Future Nostalgia (2020)', 'Dua Lipa (2017)'],
    metricsHistory: {
      listeners: [82000000, 83500000, 85000000, 86200000, 87200000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [2, 10, 13]
  },
  { 
    id: 15, 
    name: 'Travis Scott', 
    genre: ['Hip-Hop', 'Trap', 'Psychedelic'], 
    score: 92, 
    spotifyListeners: 68900000, 
    spotifyPopularity: 93,
    spotifyFollowers: 38500000,
    youtubeSubscribers: 15200000,
    youtubeViews: 9800000000,
    instagramFollowers: 58000000,
    twitterFollowers: 16200000,
    lastUpdated: '4 hours ago',
    country: 'United States',
    formedYear: 2012,
    bio: 'Travis Scott is an American rapper and producer known for his auto-tuned vocals and psychedelic production style.',
    topTracks: ['SICKO MODE', 'goosebumps', 'HIGHEST IN THE ROOM', 'Antidote', 'FE!N'],
    albums: ['UTOPIA (2023)', 'ASTROWORLD (2018)', 'Birds in the Trap Sing McKnight (2016)', 'Rodeo (2015)'],
    metricsHistory: {
      listeners: [62000000, 64500000, 66000000, 67500000, 68900000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [1, 9, 13]
  },
  { 
    id: 16, 
    name: 'SZA', 
    genre: ['R&B', 'Neo-Soul', 'Alternative R&B'], 
    score: 91, 
    spotifyListeners: 71200000, 
    spotifyPopularity: 92,
    spotifyFollowers: 28500000,
    youtubeSubscribers: 8900000,
    youtubeViews: 4200000000,
    instagramFollowers: 16500000,
    twitterFollowers: 5800000,
    lastUpdated: '2 hours ago',
    country: 'United States',
    formedYear: 2011,
    bio: 'SZA is an American singer and songwriter known for her introspective lyrics and blend of R&B, neo-soul, and hip-hop.',
    topTracks: ['Kill Bill', 'Snooze', 'Good Days', 'Kiss Me More', 'The Weekend'],
    albums: ['SOS Deluxe (2024)', 'SOS (2022)', 'Ctrl (2017)'],
    metricsHistory: {
      listeners: [65000000, 67000000, 68500000, 70000000, 71200000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [1, 13, 2]
  },
  { 
    id: 17, 
    name: 'Bad Bunny', 
    genre: ['Reggaeton', 'Latin Trap', 'Pop'], 
    score: 95, 
    spotifyListeners: 91800000, 
    spotifyPopularity: 97,
    spotifyFollowers: 48000000,
    youtubeSubscribers: 42500000,
    youtubeViews: 21000000000,
    instagramFollowers: 45000000,
    twitterFollowers: 8200000,
    lastUpdated: '1 hour ago',
    country: 'Puerto Rico',
    formedYear: 2016,
    bio: 'Bad Bunny is a Puerto Rican rapper and singer who has become one of the most influential Latin music artists globally.',
    topTracks: ['Tití Me Preguntó', 'Dakiti', 'Callaíta', 'Moscow Mule', 'Me Porto Bonito'],
    albums: ['nadie sabe lo que va a pasar mañana (2023)', 'Un Verano Sin Ti (2022)', 'El Último Tour Del Mundo (2020)', 'YHLQMDLG (2020)', 'X 100PRE (2018)'],
    metricsHistory: {
      listeners: [85000000, 87000000, 89000000, 90500000, 91800000],
      months: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    },
    tierSuggestion: 'headliner',
    similarArtists: [15, 1, 13]
  },
];

// Add generated avatar images to each artist
const mockArtists = mockArtistsData.map((artist, index) => ({
  ...artist,
  image: getAvatarUrl(artist.name, index)
}));

const allGenres = ['Hip-Hop', 'Rap', 'Pop', 'Alternative', 'Electronic', 'House', 'Techno', 'Indie', 'Rock', 'Psychedelic', 'Bedroom Pop', 'UK Garage', 'Hyperpop', 'Ambient', 'Jazz'];

const mockLineups = [
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
      undercard: [5, 6, 11, 12]
    },
    artistStatuses: {
      1: { status: 'confirmed', fee: 500000, notes: 'Headline act Saturday', contactEmail: 'booking@kendricklamar.com' },
      2: { status: 'contract_signed', fee: 450000, notes: 'Deposit received', contactEmail: 'mgmt@billieeilish.com' },
      13: { status: 'negotiating', fee: 600000, notes: 'Wants top billing Sunday night', contactEmail: 'xo@theweeknd.com' },
      14: { status: 'idea', fee: null, notes: 'Would be amazing Friday headliner', contactEmail: '' },
      15: { status: 'outreach', fee: null, notes: 'Reached out via agent 12/19', contactEmail: 'cactus@travisscott.com' },
      16: { status: 'contract_sent', fee: 420000, notes: 'DocuSign sent 12/21, waiting', contactEmail: 'tde@sza.com' },
      17: { status: 'idea', fee: null, notes: 'Latin representation - checking availability', contactEmail: '' },
      9: { status: 'contract_sent', fee: 300000, notes: 'Sent via DocuSign 12/20', contactEmail: 'tyler@golfwang.com' },
      10: { status: 'negotiating', fee: null, notes: 'Discussing fee, asking 350k', contactEmail: 'booking@charlixcx.com' },
      3: { status: 'confirmed', fee: 150000, notes: 'Special production requirements', contactEmail: 'fred@fredagain.com' },
      4: { status: 'contract_sent', fee: 120000, notes: 'Awaiting signature', contactEmail: 'mgmt@tameimpala.com' },
      7: { status: 'outreach', fee: null, notes: 'Initial email sent 12/18', contactEmail: 'bea@dirtyhit.com' },
      8: { status: 'confirmed', fee: 100000, notes: '', contactEmail: 'disclosure@island.com' },
      5: { status: 'idea', fee: null, notes: 'Would be great for Sunday afternoon', contactEmail: '' },
      6: { status: 'negotiating', fee: null, notes: 'Agent wants 80k, we offered 60k', contactEmail: 'peggy@xlrecordings.com' },
      11: { status: 'outreach', fee: null, notes: 'Waiting for response', contactEmail: 'kieran@textrecords.com' },
      12: { status: 'declined', fee: null, notes: 'Not available during festival dates', contactEmail: 'sam@ninjatune.com' },
    }
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
      undercard: [12]
    },
    artistStatuses: {
      10: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      3: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      4: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      6: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      8: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      11: { status: 'idea', fee: null, notes: '', contactEmail: '' },
      12: { status: 'idea', fee: null, notes: '', contactEmail: '' },
    }
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
      undercard: [12]
    },
    artistStatuses: {
      3: { status: 'confirmed', fee: 75000, notes: '', contactEmail: 'fred@fredagain.com' },
      8: { status: 'confirmed', fee: 50000, notes: '', contactEmail: 'disclosure@island.com' },
      6: { status: 'contract_sent', fee: 35000, notes: '', contactEmail: 'peggy@xlrecordings.com' },
      11: { status: 'outreach', fee: null, notes: '', contactEmail: '' },
      12: { status: 'idea', fee: null, notes: '', contactEmail: '' },
    }
  },
];

const tierConfig = {
  headliner: { label: 'HEADLINER', color: '#1a1a1a', bgColor: '#f0f0f0' },
  sub_headliner: { label: 'SUB-HEADLINER', color: '#404040', bgColor: '#f5f5f5' },
  mid_tier: { label: 'MID-TIER', color: '#666666', bgColor: '#f8f8f8' },
  undercard: { label: 'UNDERCARD', color: '#888888', bgColor: '#fafafa' },
};

// Shared components
const Sidebar = ({ activePage, setActivePage }) => (
  <aside className="fixed left-0 top-0 h-full w-64 bg-white border-r border-gray-200 z-40">
    <div className="p-6">
      <div className="flex items-center gap-3 mb-10">
        <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: colors.accent }}>
          <Music className="w-5 h-5 text-white" />
        </div>
        <span className="text-xl font-bold tracking-tight" style={{ color: colors.primary }}>Artist-Tree</span>
      </div>
      
      <nav className="space-y-1">
        {[
          { id: 'dashboard', icon: Home, label: 'Dashboard' },
          { id: 'search', icon: Search, label: 'Search Artists' },
          { id: 'lineups', icon: List, label: 'My Lineups' },
          { id: 'settings', icon: Settings, label: 'Settings' },
        ].map(item => (
          <button
            key={item.id}
            onClick={() => setActivePage(item.id)}
            className="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200"
            style={{
              backgroundColor: activePage === item.id ? colors.surface : 'transparent',
              color: activePage === item.id ? colors.primary : colors.secondary,
            }}
          >
            <item.icon className="w-5 h-5" />
            <span className="font-medium">{item.label}</span>
          </button>
        ))}
      </nav>
    </div>
    
    <div className="absolute bottom-6 left-6 right-6">
      <div className="p-4 rounded-xl border" style={{ backgroundColor: colors.surface, borderColor: colors.border }}>
        <div className="flex items-center gap-3 mb-2">
          <Building2 className="w-4 h-4" style={{ color: colors.accent }} />
          <span className="text-sm font-medium" style={{ color: colors.primary }}>My Organization</span>
        </div>
        <p className="text-xs" style={{ color: colors.secondary }}>Free Plan • 3 lineups</p>
      </div>
    </div>
  </aside>
);

const ScoreBadge = ({ score, size = 'sm' }) => {
  const getScoreStyle = (s) => {
    if (s >= 85) return { bg: '#dcfce7', text: '#166534' };
    if (s >= 70) return { bg: '#e0f2fe', text: '#0369a1' };
    if (s >= 55) return { bg: '#fef3c7', text: '#b45309' };
    return { bg: '#fee2e2', text: '#dc2626' };
  };
  
  const style = getScoreStyle(score);
  const sizeClasses = size === 'lg' ? 'px-4 py-2 text-xl' : size === 'md' ? 'px-3 py-1.5 text-sm' : 'px-2.5 py-1 text-xs';
  
  return (
    <div 
      className={`${sizeClasses} rounded-full font-bold`}
      style={{ backgroundColor: style.bg, color: style.text }}
    >
      {score}
    </div>
  );
};

const ArtistCard = ({ artist, onClick, compact = false }) => (
  <div 
    className={`group relative border rounded-2xl transition-all duration-200 hover:shadow-md cursor-pointer ${compact ? 'p-3' : 'p-4'}`}
    style={{ backgroundColor: colors.background, borderColor: colors.border }}
    onClick={onClick}
  >
    <div className="relative flex items-center gap-4">
      <div className="relative">
        <img 
          src={artist.image} 
          alt={artist.name}
          className={`${compact ? 'w-12 h-12' : 'w-16 h-16'} rounded-xl object-cover`}
        />
        <div className="absolute -bottom-1 -right-1">
          <ScoreBadge score={artist.score} />
        </div>
      </div>
      
      <div className="flex-1 min-w-0">
        <h3 className={`font-bold truncate ${compact ? 'text-sm' : 'text-base'}`} style={{ color: colors.primary }}>
          {artist.name}
        </h3>
        <div className="flex flex-wrap gap-1 mt-1">
          {artist.genre.slice(0, 2).map(g => (
            <span 
              key={g} 
              className="text-xs px-2 py-0.5 rounded-full"
              style={{ backgroundColor: colors.surface, color: colors.secondary }}
            >
              {g}
            </span>
          ))}
        </div>
        {!compact && (
          <div className="flex items-center gap-4 mt-2 text-xs" style={{ color: colors.secondary }}>
            <span className="flex items-center gap-1">
              <Music className="w-3 h-3" />
              {(artist.spotifyListeners / 1000000).toFixed(1)}M
            </span>
            <span className="flex items-center gap-1">
              <Youtube className="w-3 h-3" />
              {(artist.youtubeSubscribers / 1000000).toFixed(1)}M
            </span>
          </div>
        )}
      </div>
      
      <ChevronRight className="w-5 h-5 opacity-0 group-hover:opacity-100 transition-opacity" style={{ color: colors.secondary }} />
    </div>
  </div>
);

// Mini chart component
const MiniChart = ({ data, color = colors.accent }) => {
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  
  return (
    <div className="flex items-end gap-1 h-12">
      {data.map((value, i) => (
        <div 
          key={i}
          className="flex-1 rounded-sm transition-all hover:opacity-80"
          style={{ 
            height: `${((value - min) / range) * 100}%`,
            minHeight: '4px',
            backgroundColor: i === data.length - 1 ? color : `${color}40`,
          }}
        />
      ))}
    </div>
  );
};

// Add to Lineup Modal
const AddToLineupModal = ({ artist, onClose, lineups }) => {
  const [step, setStep] = useState(1); // 1 = select lineup, 2 = select tier
  const [selectedLineup, setSelectedLineup] = useState(null);
  const [selectedTier, setSelectedTier] = useState(null);
  const [added, setAdded] = useState(false);
  
  // Get suggested tier for this artist
  const suggestedTier = artist.tierSuggestion;
  
  const handleSelectLineup = (lineup) => {
    setSelectedLineup(lineup);
    setSelectedTier(suggestedTier); // Pre-select the suggested tier
    setStep(2);
  };
  
  const handleBack = () => {
    setStep(1);
    setSelectedTier(null);
  };
  
  const handleAdd = () => {
    setAdded(true);
    setTimeout(onClose, 1500);
  };
  
  const tiers = [
    { id: 'headliner', label: 'Headliner' },
    { id: 'sub_headliner', label: 'Sub-Headliner' },
    { id: 'supporting', label: 'Supporting' },
    { id: 'emerging', label: 'Emerging' },
    { id: 'local', label: 'Local/Regional' },
  ];
  
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative w-full max-w-md rounded-2xl overflow-hidden shadow-2xl p-6" style={{ backgroundColor: colors.background }}>
        {added ? (
          <div className="text-center py-8">
            <div className="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style={{ backgroundColor: '#dcfce7' }}>
              <Check className="w-8 h-8" style={{ color: '#166534' }} />
            </div>
            <h3 className="text-xl font-bold mb-2" style={{ color: colors.primary }}>Added!</h3>
            <p style={{ color: colors.secondary }}>
              {artist.name} added to {selectedLineup?.name} as {tierConfig[selectedTier]?.label}
            </p>
          </div>
        ) : (
          <>
            {/* Header */}
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-3">
                {step === 2 && (
                  <button 
                    onClick={handleBack}
                    className="p-2 rounded-lg hover:bg-gray-100 -ml-2"
                  >
                    <ChevronLeft className="w-5 h-5" style={{ color: colors.secondary }} />
                  </button>
                )}
                <h3 className="text-xl font-bold" style={{ color: colors.primary }}>
                  {step === 1 ? 'Select Lineup' : 'Select Tier'}
                </h3>
              </div>
              <button onClick={onClose} className="p-2 rounded-lg hover:bg-gray-100">
                <X className="w-5 h-5" style={{ color: colors.secondary }} />
              </button>
            </div>
            
            {/* Artist Info */}
            <div className="flex items-center gap-3 p-3 rounded-xl mb-6" style={{ backgroundColor: colors.surface }}>
              <img src={artist.image} alt={artist.name} className="w-12 h-12 rounded-lg object-cover" />
              <div className="flex-1">
                <p className="font-semibold" style={{ color: colors.primary }}>{artist.name}</p>
                <p className="text-sm" style={{ color: colors.secondary }}>Score: {artist.score}</p>
              </div>
              {step === 2 && (
                <div className="text-right">
                  <p className="text-xs" style={{ color: colors.secondary }}>Adding to</p>
                  <p className="text-sm font-medium" style={{ color: colors.primary }}>{selectedLineup?.name}</p>
                </div>
              )}
            </div>
            
            {/* Step 1: Select Lineup */}
            {step === 1 && (
              <div className="space-y-2">
                {lineups.map(lineup => (
                  <button
                    key={lineup.id}
                    onClick={() => handleSelectLineup(lineup)}
                    className="w-full flex items-center justify-between p-4 rounded-xl border transition-all hover:border-gray-300"
                    style={{ backgroundColor: colors.background, borderColor: colors.border }}
                  >
                    <div className="text-left">
                      <p className="font-medium" style={{ color: colors.primary }}>{lineup.name}</p>
                      <p className="text-sm" style={{ color: colors.secondary }}>{lineup.artistCount} artists</p>
                    </div>
                    <ChevronRight className="w-5 h-5" style={{ color: colors.secondary }} />
                  </button>
                ))}
              </div>
            )}
            
            {/* Step 2: Select Tier */}
            {step === 2 && (
              <>
                <div className="space-y-2 mb-6">
                  {tiers.map(tier => {
                    const isSuggested = tier.id === suggestedTier;
                    const isSelected = tier.id === selectedTier;
                    
                    return (
                      <button
                        key={tier.id}
                        onClick={() => setSelectedTier(tier.id)}
                        className="w-full flex items-center justify-between p-4 rounded-xl border transition-all"
                        style={{
                          backgroundColor: isSelected ? `${colors.accent}10` : colors.background,
                          borderColor: isSelected ? colors.accent : colors.border,
                        }}
                      >
                        <div className="flex items-center gap-3">
                          <div 
                            className="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                            style={{ borderColor: isSelected ? colors.accent : colors.border }}
                          >
                            {isSelected && (
                              <div className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: colors.accent }} />
                            )}
                          </div>
                          <span className="font-medium" style={{ color: colors.primary }}>{tier.label}</span>
                          {isSuggested && (
                            <span 
                              className="text-xs px-2 py-0.5 rounded-full flex items-center gap-1"
                              style={{ backgroundColor: `${colors.accent}20`, color: colors.accent }}
                            >
                              <Sparkles className="w-3 h-3" />
                              Suggested
                            </span>
                          )}
                        </div>
                        {isSelected && <Check className="w-5 h-5" style={{ color: colors.accent }} />}
                      </button>
                    );
                  })}
                </div>
                
                <button 
                  onClick={handleAdd}
                  disabled={!selectedTier}
                  className="w-full py-3 rounded-xl font-medium text-white transition-all"
                  style={{ 
                    backgroundColor: selectedTier ? colors.accent : colors.border, 
                    cursor: selectedTier ? 'pointer' : 'not-allowed' 
                  }}
                >
                  Add to Lineup
                </button>
              </>
            )}
          </>
        )}
      </div>
    </div>
  );
};

// Page: Artist Detail
const ArtistDetail = ({ artist, onBack, onNavigateToArtist }) => {
  const [showAddToLineup, setShowAddToLineup] = useState(false);
  const [showCompare, setShowCompare] = useState(false);
  const [compareWith, setCompareWith] = useState(null);
  const [compareSearch, setCompareSearch] = useState('');
  const [activeTab, setActiveTab] = useState('overview');
  
  const formatNumber = (num) => {
    if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  };
  
  const listenerChange = artist.metricsHistory.listeners[4] - artist.metricsHistory.listeners[0];
  const listenerChangePercent = ((listenerChange / artist.metricsHistory.listeners[0]) * 100).toFixed(1);
  
  const similarArtists = artist.similarArtists.map(id => mockArtists.find(a => a.id === id)).filter(Boolean);
  
  // Filter artists for comparison search (exclude current artist)
  const compareSearchResults = compareSearch.length > 0
    ? mockArtists.filter(a => a.id !== artist.id && a.name.toLowerCase().includes(compareSearch.toLowerCase()))
    : [];
  
  // Get highest value for comparison highlighting
  const getHighest = (field) => {
    if (!compareWith) return null;
    return artist[field] >= compareWith[field] ? artist.id : compareWith.id;
  };
  
  return (
    <div className="min-h-screen">
      {/* Back Button */}
      <button 
        onClick={onBack}
        className="flex items-center gap-2 mb-6 px-4 py-2 rounded-xl transition-colors hover:bg-gray-100"
        style={{ color: colors.secondary }}
      >
        <ChevronLeft className="w-5 h-5" />
        Back
      </button>
      
      {/* Header Section */}
      <div className="flex flex-col md:flex-row gap-8 mb-6">
        <img 
          src={artist.image} 
          alt={artist.name}
          className="w-40 h-40 rounded-3xl object-cover shadow-lg"
        />
        <div className="flex-1">
          <div className="flex items-start justify-between mb-3">
            <div>
              <h1 className="text-3xl font-black mb-2" style={{ color: colors.primary }}>{artist.name}</h1>
              <div className="flex flex-wrap gap-2 mb-2">
                {artist.genre.map(g => (
                  <span key={g} className="text-sm px-3 py-1 rounded-full" style={{ backgroundColor: colors.surface, color: colors.secondary }}>
                    {g}
                  </span>
                ))}
              </div>
              <div className="flex items-center gap-4 text-sm" style={{ color: colors.secondary }}>
                <span className="flex items-center gap-1">
                  <Globe className="w-4 h-4" />
                  {artist.country}
                </span>
              </div>
            </div>
            <ScoreBadge score={artist.score} size="lg" />
          </div>
          
          <div className="flex gap-3">
            <button 
              onClick={() => setShowAddToLineup(true)}
              className="flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-white transition-all hover:opacity-90"
              style={{ backgroundColor: colors.accent }}
            >
              <Plus className="w-4 h-4" />
              Add to Lineup
            </button>
            <button 
              onClick={() => setShowCompare(true)}
              className="flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium transition-all hover:opacity-90"
              style={{ backgroundColor: colors.surface, color: colors.primary }}
            >
              <BarChart3 className="w-4 h-4" />
              Compare
            </button>
          </div>
        </div>
      </div>
      
      {/* Tabs */}
      <div className="flex gap-1 mb-6 border-b" style={{ borderColor: colors.border }}>
        {[
          { id: 'overview', label: 'Overview', icon: Info },
          { id: 'data', label: 'Data & Metrics', icon: BarChart3 },
        ].map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className="flex items-center gap-2 px-5 py-3 font-medium transition-all border-b-2 -mb-px"
            style={{
              borderColor: activeTab === tab.id ? colors.accent : 'transparent',
              color: activeTab === tab.id ? colors.primary : colors.secondary,
            }}
          >
            <tab.icon className="w-4 h-4" />
            {tab.label}
          </button>
        ))}
      </div>
      
      {/* Overview Tab */}
      {activeTab === 'overview' && (
        <div>
          {/* Bio */}
          <div className="mb-6">
            <p className="text-base leading-relaxed" style={{ color: colors.secondary }}>{artist.bio}</p>
          </div>
          
          {/* Quick Stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div className="p-4 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <p className="text-xs mb-1" style={{ color: colors.secondary }}>Monthly Listeners</p>
              <p className="text-xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.spotifyListeners)}</p>
            </div>
            <div className="p-4 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <p className="text-xs mb-1" style={{ color: colors.secondary }}>Spotify Popularity</p>
              <p className="text-xl font-bold" style={{ color: colors.primary }}>{artist.spotifyPopularity}/100</p>
            </div>
            <div className="p-4 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <p className="text-xs mb-1" style={{ color: colors.secondary }}>YouTube Subs</p>
              <p className="text-xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.youtubeSubscribers)}</p>
            </div>
            <div className="p-4 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <p className="text-xs mb-1" style={{ color: colors.secondary }}>Instagram</p>
              <p className="text-xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.instagramFollowers)}</p>
            </div>
          </div>
          
          {/* Top Tracks & Discography */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <h3 className="font-bold mb-3" style={{ color: colors.primary }}>Top Tracks</h3>
              <div className="space-y-2">
                {artist.topTracks.slice(0, 5).map((track, i) => (
                  <div key={track} className="flex items-center gap-3 py-1">
                    <span className="text-sm font-medium w-5" style={{ color: colors.secondary }}>{i + 1}</span>
                    <span className="text-sm" style={{ color: colors.primary }}>{track}</span>
                  </div>
                ))}
              </div>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <h3 className="font-bold mb-3" style={{ color: colors.primary }}>Recent Releases</h3>
              <div className="space-y-2">
                {artist.albums.slice(0, 5).map(album => (
                  <div key={album} className="flex items-center gap-2 py-1">
                    <Music className="w-4 h-4" style={{ color: colors.secondary }} />
                    <span className="text-sm" style={{ color: colors.primary }}>{album}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
          
          {/* Similar Artists */}
          <div className="mb-6">
            <h3 className="font-bold mb-4" style={{ color: colors.primary }}>Similar Artists</h3>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
              {similarArtists.map(similar => (
                <div 
                  key={similar.id}
                  onClick={() => onNavigateToArtist(similar)}
                  className="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all hover:shadow-md"
                  style={{ backgroundColor: colors.background, borderColor: colors.border }}
                >
                  <img src={similar.image} alt={similar.name} className="w-12 h-12 rounded-xl object-cover" />
                  <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm truncate" style={{ color: colors.primary }}>{similar.name}</p>
                    <p className="text-xs" style={{ color: colors.secondary }}>{similar.genre[0]}</p>
                  </div>
                  <ScoreBadge score={similar.score} size="sm" />
                </div>
              ))}
            </div>
          </div>
          
          {/* External Links */}
          <div>
            <h3 className="font-bold mb-4" style={{ color: colors.primary }}>External Links</h3>
            <div className="flex flex-wrap gap-3">
              <a 
                href="#" 
                className="flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium transition-colors hover:opacity-80"
                style={{ backgroundColor: '#1DB954', color: 'white' }}
              >
                <Music className="w-4 h-4" />
                Spotify
                <ExternalLink className="w-3 h-3 opacity-70" />
              </a>
              <a 
                href="#" 
                className="flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium transition-colors hover:opacity-80"
                style={{ backgroundColor: '#FF0000', color: 'white' }}
              >
                <Youtube className="w-4 h-4" />
                YouTube
                <ExternalLink className="w-3 h-3 opacity-70" />
              </a>
              <a 
                href="#" 
                className="flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium transition-colors hover:opacity-80"
                style={{ backgroundColor: '#E4405F', color: 'white' }}
              >
                <Instagram className="w-4 h-4" />
                Instagram
                <ExternalLink className="w-3 h-3 opacity-70" />
              </a>
              <a 
                href="#" 
                className="flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium transition-colors hover:opacity-80"
                style={{ backgroundColor: colors.primary, color: 'white' }}
              >
                <Twitter className="w-4 h-4" />
                X / Twitter
                <ExternalLink className="w-3 h-3 opacity-70" />
              </a>
            </div>
          </div>
        </div>
      )}
      
      {/* Data Tab */}
      {activeTab === 'data' && (
        <div>
          {/* Main Metrics Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Music className="w-4 h-4" style={{ color: '#1DB954' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Monthly Listeners</span>
              </div>
              <p className="text-2xl font-bold mb-1" style={{ color: colors.primary }}>{formatNumber(artist.spotifyListeners)}</p>
              <div className="flex items-center gap-1 text-xs" style={{ color: listenerChange >= 0 ? '#166534' : '#dc2626' }}>
                {listenerChange >= 0 ? <ArrowUp className="w-3 h-3" /> : <ArrowDown className="w-3 h-3" />}
                {listenerChangePercent}% vs 5 mo ago
              </div>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <TrendingUp className="w-4 h-4" style={{ color: '#1DB954' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Spotify Popularity</span>
              </div>
              <p className="text-2xl font-bold mb-1" style={{ color: colors.primary }}>{artist.spotifyPopularity}</p>
              <p className="text-xs" style={{ color: colors.secondary }}>Out of 100</p>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Users className="w-4 h-4" style={{ color: '#1DB954' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Spotify Followers</span>
              </div>
              <p className="text-2xl font-bold mb-1" style={{ color: colors.primary }}>{formatNumber(artist.spotifyFollowers)}</p>
              <p className="text-xs" style={{ color: colors.secondary }}>Total followers</p>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Youtube className="w-4 h-4" style={{ color: '#FF0000' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>YouTube Subscribers</span>
              </div>
              <p className="text-2xl font-bold mb-1" style={{ color: colors.primary }}>{formatNumber(artist.youtubeSubscribers)}</p>
              <p className="text-xs" style={{ color: colors.secondary }}>{formatNumber(artist.youtubeViews)} views</p>
            </div>
          </div>
          
          {/* Social Media Stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Instagram className="w-4 h-4" style={{ color: '#E4405F' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Instagram</span>
              </div>
              <p className="text-2xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.instagramFollowers)}</p>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Twitter className="w-4 h-4" style={{ color: colors.primary }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Twitter/X</span>
              </div>
              <p className="text-2xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.twitterFollowers)}</p>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Youtube className="w-4 h-4" style={{ color: '#FF0000' }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Total YT Views</span>
              </div>
              <p className="text-2xl font-bold" style={{ color: colors.primary }}>{formatNumber(artist.youtubeViews)}</p>
            </div>
            
            <div className="p-5 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center gap-2 mb-2">
                <Calendar className="w-4 h-4" style={{ color: colors.secondary }} />
                <span className="text-xs font-medium" style={{ color: colors.secondary }}>Active Since</span>
              </div>
              <p className="text-2xl font-bold" style={{ color: colors.primary }}>{artist.formedYear}</p>
            </div>
          </div>
          
          {/* Charts Row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {/* Listener Trend */}
            <div className="p-6 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold" style={{ color: colors.primary }}>Monthly Listeners Trend</h3>
                <span className="text-sm" style={{ color: colors.secondary }}>Last 5 months</span>
              </div>
              <div className="h-32 flex items-end gap-2">
                {artist.metricsHistory.listeners.map((value, i) => {
                  const max = Math.max(...artist.metricsHistory.listeners);
                  const height = (value / max) * 100;
                  return (
                    <div key={i} className="flex-1 flex flex-col items-center gap-2">
                      <div 
                        className="w-full rounded-t-lg transition-all hover:opacity-80"
                        style={{ 
                          height: `${height}%`,
                          backgroundColor: i === artist.metricsHistory.listeners.length - 1 ? colors.accent : `${colors.accent}40`,
                        }}
                      />
                      <span className="text-xs" style={{ color: colors.secondary }}>{artist.metricsHistory.months[i]}</span>
                    </div>
                  );
                })}
              </div>
            </div>
            
            {/* Score Breakdown */}
            <div className="p-6 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-bold" style={{ color: colors.primary }}>Score Breakdown</h3>
                <ScoreBadge score={artist.score} size="md" />
              </div>
              <div className="space-y-4">
                {[
                  { label: 'Spotify Monthly Listeners', value: 40, weight: '40%', raw: formatNumber(artist.spotifyListeners) },
                  { label: 'Spotify Popularity', value: 30, weight: '30%', raw: artist.spotifyPopularity + '/100' },
                  { label: 'YouTube Subscribers', value: 24, weight: '30%', raw: formatNumber(artist.youtubeSubscribers) },
                ].map(metric => (
                  <div key={metric.label}>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm" style={{ color: colors.primary }}>{metric.label}</span>
                      <span className="text-xs" style={{ color: colors.secondary }}>{metric.raw}</span>
                    </div>
                    <div className="h-2 rounded-full" style={{ backgroundColor: colors.surface }}>
                      <div 
                        className="h-full rounded-full"
                        style={{ width: `${metric.value}%`, backgroundColor: colors.accent }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
          
          {/* Data Freshness */}
          <div className="flex items-center justify-between p-4 rounded-2xl" style={{ backgroundColor: colors.surface }}>
            <div className="flex items-center gap-2 text-sm" style={{ color: colors.secondary }}>
              <Clock className="w-4 h-4" />
              Data last updated {artist.lastUpdated}
            </div>
            <button className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-colors hover:bg-white" style={{ color: colors.accent }}>
              <RefreshCw className="w-4 h-4" />
              Refresh Data
            </button>
          </div>
        </div>
      )}
      
      {/* Add to Lineup Modal */}
      {showAddToLineup && (
        <AddToLineupModal
          artist={artist}
          lineups={mockLineups}
          onClose={() => setShowAddToLineup(false)}
        />
      )}
      
      {/* Compare Modal */}
      {showCompare && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => { setShowCompare(false); setCompareWith(null); setCompareSearch(''); }} />
          <div 
            className="relative w-full max-w-4xl max-h-[90vh] overflow-auto rounded-2xl shadow-2xl"
            style={{ backgroundColor: colors.background }}
          >
            {/* Modal Header */}
            <div className="sticky top-0 z-10 flex items-center justify-between p-6 border-b" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div>
                <h2 className="text-xl font-bold" style={{ color: colors.primary }}>Compare Artists</h2>
                <p className="text-sm" style={{ color: colors.secondary }}>
                  {compareWith ? `Comparing ${artist.name} vs ${compareWith.name}` : `Select an artist to compare with ${artist.name}`}
                </p>
              </div>
              <button 
                onClick={() => { setShowCompare(false); setCompareWith(null); setCompareSearch(''); }}
                className="p-2 rounded-xl hover:bg-gray-100 transition-colors"
              >
                <X className="w-5 h-5" style={{ color: colors.secondary }} />
              </button>
            </div>
            
            <div className="p-6">
              {!compareWith ? (
                /* Artist Selection */
                <div>
                  <div className="relative mb-4">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5" style={{ color: colors.secondary }} />
                    <input
                      type="text"
                      value={compareSearch}
                      onChange={(e) => setCompareSearch(e.target.value)}
                      placeholder="Search for an artist to compare..."
                      className="w-full pl-12 pr-4 py-3 border rounded-xl focus:outline-none focus:ring-2"
                      style={{ borderColor: colors.border, color: colors.primary }}
                      autoFocus
                    />
                  </div>
                  
                  {compareSearch.length > 0 && (
                    <div className="space-y-2 mb-4">
                      {compareSearchResults.length > 0 ? (
                        compareSearchResults.slice(0, 6).map(a => (
                          <button
                            key={a.id}
                            onClick={() => { setCompareWith(a); setCompareSearch(''); }}
                            className="w-full flex items-center gap-4 p-3 rounded-xl border transition-all hover:border-gray-300"
                            style={{ backgroundColor: colors.background, borderColor: colors.border }}
                          >
                            <img src={a.image} alt={a.name} className="w-12 h-12 rounded-xl object-cover" />
                            <div className="flex-1 text-left">
                              <p className="font-semibold" style={{ color: colors.primary }}>{a.name}</p>
                              <p className="text-sm" style={{ color: colors.secondary }}>{a.genre.slice(0, 2).join(' • ')}</p>
                            </div>
                            <ScoreBadge score={a.score} size="sm" />
                          </button>
                        ))
                      ) : (
                        <p className="text-center py-4" style={{ color: colors.secondary }}>No artists found</p>
                      )}
                    </div>
                  )}
                  
                  {/* Similar Artists Suggestions */}
                  {compareSearch.length === 0 && (
                    <div>
                      <p className="text-sm font-medium mb-3" style={{ color: colors.secondary }}>Suggested comparisons</p>
                      <div className="space-y-2">
                        {similarArtists.map(a => (
                          <button
                            key={a.id}
                            onClick={() => setCompareWith(a)}
                            className="w-full flex items-center gap-4 p-3 rounded-xl border transition-all hover:border-gray-300"
                            style={{ backgroundColor: colors.background, borderColor: colors.border }}
                          >
                            <img src={a.image} alt={a.name} className="w-12 h-12 rounded-xl object-cover" />
                            <div className="flex-1 text-left">
                              <p className="font-semibold" style={{ color: colors.primary }}>{a.name}</p>
                              <p className="text-sm" style={{ color: colors.secondary }}>{a.genre.slice(0, 2).join(' • ')}</p>
                            </div>
                            <ScoreBadge score={a.score} size="sm" />
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ) : (
                /* Comparison View */
                <div>
                  {/* Table Header with Artist Info */}
                  <div className="grid grid-cols-3 gap-4 mb-2">
                    <div></div>
                    <div className="text-center p-4 rounded-2xl" style={{ backgroundColor: colors.surface }}>
                      <img src={artist.image} alt={artist.name} className="w-16 h-16 rounded-xl object-cover mx-auto mb-2" />
                      <h3 className="font-bold" style={{ color: colors.primary }}>{artist.name}</h3>
                      <p className="text-xs" style={{ color: colors.secondary }}>{artist.genre[0]}</p>
                    </div>
                    <div className="text-center p-4 rounded-2xl" style={{ backgroundColor: colors.surface }}>
                      <img src={compareWith.image} alt={compareWith.name} className="w-16 h-16 rounded-xl object-cover mx-auto mb-2" />
                      <h3 className="font-bold" style={{ color: colors.primary }}>{compareWith.name}</h3>
                      <p className="text-xs" style={{ color: colors.secondary }}>{compareWith.genre[0]}</p>
                    </div>
                  </div>
                  
                  {/* Comparison Rows */}
                  <div className="space-y-1">
                    {[
                      { label: 'Artist Score', field: 'score', icon: Award },
                      { label: 'Monthly Listeners', field: 'spotifyListeners', icon: Music, format: true },
                      { label: 'Spotify Popularity', field: 'spotifyPopularity', icon: TrendingUp },
                      { label: 'Spotify Followers', field: 'spotifyFollowers', icon: Users, format: true },
                      { label: 'YouTube Subscribers', field: 'youtubeSubscribers', icon: Youtube, format: true },
                      { label: 'Instagram Followers', field: 'instagramFollowers', icon: Instagram, format: true },
                      { label: 'Twitter Followers', field: 'twitterFollowers', icon: Twitter, format: true },
                    ].map((row, i) => {
                      const highest = getHighest(row.field);
                      return (
                        <div 
                          key={row.label}
                          className="grid grid-cols-3 gap-4 p-3 rounded-xl items-center"
                          style={{ backgroundColor: i % 2 === 0 ? colors.surface : 'transparent' }}
                        >
                          <div className="flex items-center gap-2" style={{ color: colors.primary }}>
                            <row.icon className="w-4 h-4" style={{ color: colors.accent }} />
                            <span className="font-medium text-sm">{row.label}</span>
                          </div>
                          <div className="text-center">
                            <span 
                              className="font-bold text-lg"
                              style={{ color: highest === artist.id ? '#059669' : colors.primary }}
                            >
                              {row.format ? formatNumber(artist[row.field]) : artist[row.field]}
                            </span>
                            {highest === artist.id && <span className="ml-1 text-xs" style={{ color: '#059669' }}>★</span>}
                          </div>
                          <div className="text-center">
                            <span 
                              className="font-bold text-lg"
                              style={{ color: highest === compareWith.id ? '#059669' : colors.primary }}
                            >
                              {row.format ? formatNumber(compareWith[row.field]) : compareWith[row.field]}
                            </span>
                            {highest === compareWith.id && <span className="ml-1 text-xs" style={{ color: '#059669' }}>★</span>}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                  
                  {/* Change Artist Button */}
                  <div className="mt-6 pt-6 border-t flex justify-center" style={{ borderColor: colors.border }}>
                    <button
                      onClick={() => setCompareWith(null)}
                      className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-colors"
                      style={{ backgroundColor: colors.surface, color: colors.primary }}
                    >
                      <RefreshCw className="w-4 h-4" />
                      Compare with different artist
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// Page: Artist Search
const ArtistSearch = ({ onNavigate, onSelectArtist }) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedGenres, setSelectedGenres] = useState([]);
  const [scoreRange, setScoreRange] = useState([0, 100]);
  const [sortBy, setSortBy] = useState('score');
  const [showFilters, setShowFilters] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);
  
  const filteredArtists = mockArtists
    .filter(a => {
      const matchesSearch = searchQuery === '' || a.name.toLowerCase().includes(searchQuery.toLowerCase());
      const matchesGenre = selectedGenres.length === 0 || a.genre.some(g => selectedGenres.includes(g));
      const matchesScore = a.score >= scoreRange[0] && a.score <= scoreRange[1];
      return matchesSearch && matchesGenre && matchesScore;
    })
    .sort((a, b) => {
      if (sortBy === 'score') return b.score - a.score;
      if (sortBy === 'name') return a.name.localeCompare(b.name);
      if (sortBy === 'listeners') return b.spotifyListeners - a.spotifyListeners;
      return 0;
    });
  
  // Get similar artists based on search results (by genre overlap)
  const getSimilarArtists = () => {
    if (filteredArtists.length === 0 || !hasSearched || searchQuery === '') return [];
    const searchedGenres = filteredArtists.flatMap(a => a.genre);
    const genreCounts = searchedGenres.reduce((acc, g) => ({ ...acc, [g]: (acc[g] || 0) + 1 }), {});
    const topGenres = Object.entries(genreCounts).sort((a, b) => b[1] - a[1]).slice(0, 3).map(([g]) => g);
    
    return mockArtists
      .filter(a => !filteredArtists.some(fa => fa.id === a.id))
      .filter(a => a.genre.some(g => topGenres.includes(g)))
      .sort((a, b) => b.score - a.score)
      .slice(0, 5);
  };
  
  const similarArtists = getSimilarArtists();
  
  // Trending artists (top by listeners, not in search results)
  const trendingArtists = mockArtists
    .sort((a, b) => b.spotifyListeners - a.spotifyListeners)
    .slice(0, 10);
  
  const toggleGenre = (genre) => {
    setSelectedGenres(prev => prev.includes(genre) ? prev.filter(g => g !== genre) : [...prev, genre]);
  };
  
  const handleSearch = () => {
    setIsSearching(true);
    setHasSearched(true);
    setTimeout(() => setIsSearching(false), 500);
  };
  
  // Compact Artist Card component
  const CompactArtistCard = ({ artist }) => (
    <div 
      onClick={() => onSelectArtist(artist)}
      className="group border rounded-2xl p-4 cursor-pointer transition-all hover:shadow-lg hover:border-gray-300"
      style={{ backgroundColor: colors.background, borderColor: colors.border }}
    >
      <div className="flex items-start gap-3 mb-3">
        <img 
          src={artist.image} 
          alt={artist.name}
          className="w-14 h-14 rounded-xl object-cover"
        />
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <h3 className="font-bold truncate" style={{ color: colors.primary }}>{artist.name}</h3>
            <ScoreBadge score={artist.score} size="sm" />
          </div>
          <p className="text-sm truncate" style={{ color: colors.secondary }}>
            {artist.genre.slice(0, 2).join(' • ')}
          </p>
        </div>
      </div>
      <div className="flex items-center justify-between text-xs" style={{ color: colors.secondary }}>
        <span>{(artist.spotifyListeners / 1000000).toFixed(1)}M listeners</span>
        <ChevronRight className="w-4 h-4 opacity-0 group-hover:opacity-100 transition-opacity" style={{ color: colors.accent }} />
      </div>
    </div>
  );
  
  return (
    <div className="min-h-screen">
      <div className="mb-8">
        <h1 className="text-3xl font-black mb-2" style={{ color: colors.primary }}>Search Artists</h1>
        <p style={{ color: colors.secondary }}>Find and discover artists to add to your lineups</p>
      </div>
      
      <div className="flex gap-3 mb-6">
        <div className="flex-1 relative">
          <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <Search className="w-5 h-5" style={{ color: colors.secondary }} />
          </div>
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            placeholder="Search by artist name..."
            className="w-full pl-12 pr-4 py-4 border rounded-2xl transition-all focus:outline-none focus:ring-2"
            style={{ backgroundColor: colors.background, borderColor: colors.border, color: colors.primary }}
          />
        </div>
        <button 
          onClick={() => setShowFilters(!showFilters)}
          className="px-4 py-4 rounded-2xl border transition-colors flex items-center gap-2"
          style={{ backgroundColor: showFilters ? colors.surface : colors.background, borderColor: colors.border, color: colors.primary }}
        >
          <SlidersHorizontal className="w-5 h-5" />
          Filters
          {(selectedGenres.length > 0 || scoreRange[0] > 0 || scoreRange[1] < 100) && (
            <span className="w-5 h-5 rounded-full text-xs flex items-center justify-center text-white" style={{ backgroundColor: colors.accent }}>
              {selectedGenres.length + (scoreRange[0] > 0 || scoreRange[1] < 100 ? 1 : 0)}
            </span>
          )}
        </button>
        <button 
          onClick={handleSearch}
          className="px-6 py-4 rounded-2xl font-medium text-white transition-all hover:opacity-90"
          style={{ backgroundColor: colors.accent }}
        >
          Search
        </button>
      </div>
      
      {showFilters && (
        <div className="mb-6 p-6 rounded-2xl border" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h4 className="font-semibold mb-3" style={{ color: colors.primary }}>Genres</h4>
              <div className="flex flex-wrap gap-2">
                {allGenres.map(genre => (
                  <button
                    key={genre}
                    onClick={() => toggleGenre(genre)}
                    className="px-3 py-1.5 rounded-full text-sm transition-all"
                    style={{
                      backgroundColor: selectedGenres.includes(genre) ? colors.accent : colors.surface,
                      color: selectedGenres.includes(genre) ? 'white' : colors.secondary,
                    }}
                  >
                    {genre}
                  </button>
                ))}
              </div>
            </div>
            <div>
              <h4 className="font-semibold mb-3" style={{ color: colors.primary }}>Score Range</h4>
              <div className="flex items-center gap-4">
                <input
                  type="number"
                  min="0"
                  max="100"
                  value={scoreRange[0]}
                  onChange={(e) => setScoreRange([parseInt(e.target.value) || 0, scoreRange[1]])}
                  className="w-20 px-3 py-2 border rounded-xl text-center"
                  style={{ borderColor: colors.border, color: colors.primary }}
                />
                <span style={{ color: colors.secondary }}>to</span>
                <input
                  type="number"
                  min="0"
                  max="100"
                  value={scoreRange[1]}
                  onChange={(e) => setScoreRange([scoreRange[0], parseInt(e.target.value) || 100])}
                  className="w-20 px-3 py-2 border rounded-xl text-center"
                  style={{ borderColor: colors.border, color: colors.primary }}
                />
              </div>
            </div>
          </div>
          {(selectedGenres.length > 0 || scoreRange[0] > 0 || scoreRange[1] < 100) && (
            <button
              onClick={() => { setSelectedGenres([]); setScoreRange([0, 100]); }}
              className="mt-4 text-sm flex items-center gap-1"
              style={{ color: colors.accent }}
            >
              <X className="w-4 h-4" />
              Clear all filters
            </button>
          )}
        </div>
      )}
      
      {/* Search Results */}
      {hasSearched && (
        <>
          <div className="flex items-center justify-between mb-4">
            <p style={{ color: colors.secondary }}>
              {filteredArtists.length} artists found
            </p>
            <div className="flex items-center gap-2">
              <span className="text-sm" style={{ color: colors.secondary }}>Sort by:</span>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="px-3 py-2 rounded-xl border text-sm"
                style={{ borderColor: colors.border, color: colors.primary, backgroundColor: colors.background }}
              >
                <option value="score">Score (High to Low)</option>
                <option value="name">Name (A-Z)</option>
                <option value="listeners">Monthly Listeners</option>
              </select>
            </div>
          </div>
          
          {isSearching ? (
            <div className="flex items-center justify-center py-20">
              <RefreshCw className="w-8 h-8 animate-spin" style={{ color: colors.accent }} />
            </div>
          ) : filteredArtists.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-8">
              {filteredArtists.map(artist => (
                <CompactArtistCard key={artist.id} artist={artist} />
              ))}
            </div>
          ) : (
            <div className="text-center py-20 mb-8">
              <div className="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style={{ backgroundColor: colors.surface }}>
                <Search className="w-8 h-8" style={{ color: colors.secondary }} />
              </div>
              <h3 className="text-xl font-bold mb-2" style={{ color: colors.primary }}>No artists found</h3>
              <p style={{ color: colors.secondary }}>Try adjusting your search or filters</p>
            </div>
          )}
          
          {/* Similar Artists Section */}
          {similarArtists.length > 0 && (
            <div className="mb-8">
              <h2 className="text-xl font-bold mb-4" style={{ color: colors.primary }}>Similar Artists</h2>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                {similarArtists.map(artist => (
                  <CompactArtistCard key={artist.id} artist={artist} />
                ))}
              </div>
            </div>
          )}
        </>
      )}
      
      {/* Trending Artists Section */}
      <div>
        <h2 className="text-xl font-bold mb-4" style={{ color: colors.primary }}>Trending Artists</h2>
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          {trendingArtists.map(artist => (
            <CompactArtistCard key={artist.id} artist={artist} />
          ))}
        </div>
      </div>
    </div>
  );
};

// Page: Dashboard
const Dashboard = ({ onNavigate, onSelectArtist }) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  
  const handleSearch = (query) => {
    setSearchQuery(query);
    if (query.length > 1) {
      setIsSearching(true);
      setTimeout(() => {
        setSearchResults(mockArtists.filter(a => a.name.toLowerCase().includes(query.toLowerCase())));
        setIsSearching(false);
      }, 300);
    } else {
      setSearchResults([]);
    }
  };
  
  // Calculate stats for each lineup
  const getLineupStats = (lineup) => {
    const allArtistIds = Object.values(lineup.artists).flat();
    const artists = allArtistIds.map(id => mockArtists.find(a => a.id === id)).filter(Boolean);
    const avgScore = artists.length > 0 
      ? Math.round(artists.reduce((sum, a) => sum + a.score, 0) / artists.length)
      : 0;
    
    const statuses = lineup.artistStatuses || {};
    const confirmed = Object.values(statuses).filter(s => s.status === 'confirmed').length;
    const pending = Object.values(statuses).filter(s => ['contract_sent', 'contract_signed', 'negotiating', 'outreach'].includes(s.status)).length;
    const totalBudget = Object.values(statuses)
      .filter(s => s.fee && s.status !== 'declined')
      .reduce((sum, s) => sum + s.fee, 0);
    
    return { 
      artistCount: artists.length, 
      avgScore, 
      confirmed, 
      pending,
      totalBudget
    };
  };
  
  return (
    <div className="min-h-screen">
      <div className="relative rounded-3xl border p-8 mb-8" style={{ backgroundColor: colors.surface, borderColor: colors.border }}>
        <div>
          <h1 className="text-4xl md:text-5xl font-black tracking-tight mb-3" style={{ color: colors.primary }}>
            Build Your <span style={{ color: colors.accent }}>Dream Lineup</span>
          </h1>
          <p className="text-lg mb-6 max-w-xl" style={{ color: colors.secondary }}>
            Discover artists, analyze metrics, and create balanced festival lineups with data-driven tier suggestions.
          </p>
          
          <div className="relative w-full" style={{ zIndex: 100 }}>
            <div className="absolute inset-y-0 left-4 flex items-center pointer-events-none">
              <Search className="w-5 h-5" style={{ color: colors.secondary }} />
            </div>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => handleSearch(e.target.value)}
              placeholder="Search for any artist..."
              className="w-full pl-12 pr-4 py-4 border rounded-2xl transition-all focus:outline-none focus:ring-2"
              style={{ backgroundColor: colors.background, borderColor: colors.border, color: colors.primary }}
            />
            {isSearching && (
              <div className="absolute inset-y-0 right-4 flex items-center">
                <RefreshCw className="w-5 h-5 animate-spin" style={{ color: colors.secondary }} />
              </div>
            )}
          
            {searchResults.length > 0 && (
              <div className="absolute left-0 right-0 mt-2 border rounded-2xl overflow-hidden shadow-2xl" style={{ backgroundColor: colors.background, borderColor: colors.border, zIndex: 9999 }}>
                {searchResults.slice(0, 5).map(artist => (
                  <div 
                    key={artist.id}
                    onClick={() => { setSearchQuery(''); setSearchResults([]); onSelectArtist(artist); }}
                    className="flex items-center gap-4 p-4 cursor-pointer transition-colors border-b last:border-0 hover:bg-gray-50"
                    style={{ borderColor: colors.border }}
                  >
                    <img src={artist.image} alt={artist.name} className="w-12 h-12 rounded-xl object-cover" />
                    <div className="flex-1">
                      <div className="font-semibold" style={{ color: colors.primary }}>{artist.name}</div>
                      <div className="text-sm" style={{ color: colors.secondary }}>{artist.genre.join(' • ')}</div>
                    </div>
                    <ScoreBadge score={artist.score} />
                    <ChevronRight className="w-4 h-4" style={{ color: colors.secondary }} />
                  </div>
                ))}
                <button
                  onClick={() => { setSearchQuery(''); setSearchResults([]); onNavigate('search'); }}
                  className="w-full p-3 text-sm font-medium flex items-center justify-center gap-2 hover:bg-gray-50"
                  style={{ color: colors.accent }}
                >
                View all results
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          )}
          </div>
        </div>
      </div>
      
      {/* Lineup Stats Section */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-bold" style={{ color: colors.primary }}>Your Lineups</h2>
          <button onClick={() => onNavigate('lineups')} className="text-sm font-medium flex items-center gap-1" style={{ color: colors.accent }}>
            View all
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
        <div className="space-y-4">
          {mockLineups.map(lineup => {
            const stats = getLineupStats(lineup);
            return (
              <div 
                key={lineup.id}
                className="border rounded-2xl p-5 cursor-pointer transition-all hover:shadow-md"
                style={{ backgroundColor: colors.background, borderColor: colors.border }}
                onClick={() => onNavigate('lineups')}
              >
                <div className="flex items-center justify-between mb-4">
                  <div>
                    <h3 className="font-bold text-lg" style={{ color: colors.primary }}>{lineup.name}</h3>
                    <p className="text-sm" style={{ color: colors.secondary }}>{lineup.description}</p>
                  </div>
                  <ChevronRight className="w-5 h-5" style={{ color: colors.secondary }} />
                </div>
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <div className="p-3 rounded-xl" style={{ backgroundColor: colors.surface }}>
                    <p className="text-xs mb-1" style={{ color: colors.secondary }}>Artists</p>
                    <p className="text-xl font-bold" style={{ color: colors.primary }}>{stats.artistCount}</p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: colors.surface }}>
                    <p className="text-xs mb-1" style={{ color: colors.secondary }}>Avg. Score</p>
                    <p className="text-xl font-bold" style={{ color: colors.primary }}>{stats.avgScore}</p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: colors.surface }}>
                    <p className="text-xs mb-1" style={{ color: colors.secondary }}>Confirmed</p>
                    <p className="text-xl font-bold" style={{ color: '#059669' }}>{stats.confirmed}</p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: colors.surface }}>
                    <p className="text-xs mb-1" style={{ color: colors.secondary }}>Pending</p>
                    <p className="text-xl font-bold" style={{ color: '#f59e0b' }}>{stats.pending}</p>
                  </div>
                  <div className="p-3 rounded-xl" style={{ backgroundColor: colors.surface }}>
                    <p className="text-xs mb-1" style={{ color: colors.secondary }}>Budget</p>
                    <p className="text-xl font-bold" style={{ color: colors.primary }}>${(stats.totalBudget / 1000).toFixed(0)}k</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

// Page: Organization Settings
const OrgSettings = () => {
  const [activeTab, setActiveTab] = useState('weights');
  const [preset, setPreset] = useState('balanced');
  const [weights, setWeights] = useState({
    spotify_monthly_listeners: 0.40,
    spotify_popularity: 0.30,
    youtube_subscribers: 0.30,
  });
  
  const presets = {
    balanced: { spotify_monthly_listeners: 0.40, spotify_popularity: 0.30, youtube_subscribers: 0.30 },
    streaming_focused: { spotify_monthly_listeners: 0.55, spotify_popularity: 0.30, youtube_subscribers: 0.15 },
    social_media_focused: { spotify_monthly_listeners: 0.20, spotify_popularity: 0.15, youtube_subscribers: 0.65 },
  };
  
  const applyPreset = (presetName) => { setPreset(presetName); setWeights(presets[presetName]); };
  const updateWeight = (metric, value) => { setWeights(prev => ({ ...prev, [metric]: value })); setPreset('custom'); };
  
  const totalWeight = Object.values(weights).reduce((a, b) => a + b, 0);
  const isValid = Math.abs(totalWeight - 1) < 0.01;
  
  return (
    <div className="min-h-screen max-w-4xl">
      <h1 className="text-3xl font-black mb-2" style={{ color: colors.primary }}>Organization Settings</h1>
      <p className="mb-8" style={{ color: colors.secondary }}>Configure scoring weights and manage team members</p>
      
      <div className="flex gap-2 mb-8">
        {[{ id: 'weights', label: 'Scoring Weights' }, { id: 'members', label: 'Team Members' }].map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className="px-5 py-2.5 rounded-xl font-medium transition-all"
            style={{ backgroundColor: activeTab === tab.id ? colors.primary : colors.surface, color: activeTab === tab.id ? colors.background : colors.secondary }}
          >
            {tab.label}
          </button>
        ))}
      </div>
      
      {activeTab === 'weights' && (
        <div className="space-y-6">
          <div className="p-6 border rounded-2xl" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
            <h3 className="font-bold mb-4" style={{ color: colors.primary }}>Quick Presets</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              {[
                { id: 'balanced', label: 'Balanced', desc: 'Equal emphasis across platforms' },
                { id: 'streaming_focused', label: 'Streaming Focus', desc: 'Prioritize Spotify metrics' },
                { id: 'social_media_focused', label: 'Social Focus', desc: 'Prioritize YouTube presence' },
              ].map(p => (
                <button
                  key={p.id}
                  onClick={() => applyPreset(p.id)}
                  className="p-4 rounded-xl border text-left transition-all"
                  style={{ borderColor: preset === p.id ? colors.accent : colors.border, backgroundColor: preset === p.id ? `${colors.accent}10` : colors.background }}
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-semibold" style={{ color: colors.primary }}>{p.label}</span>
                    {preset === p.id && <Check className="w-4 h-4" style={{ color: colors.accent }} />}
                  </div>
                  <p className="text-sm" style={{ color: colors.secondary }}>{p.desc}</p>
                </button>
              ))}
            </div>
          </div>
          
          <div className="p-6 border rounded-2xl" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="font-bold" style={{ color: colors.primary }}>Custom Weights</h3>
                <p className="text-sm" style={{ color: colors.secondary }}>Weights must sum to 100%</p>
              </div>
              <div className="px-3 py-1.5 rounded-full text-sm font-medium" style={{ backgroundColor: isValid ? '#dcfce7' : '#fee2e2', color: isValid ? '#166534' : '#dc2626' }}>
                Total: {(totalWeight * 100).toFixed(0)}%
              </div>
            </div>
            <div className="space-y-6">
              {[
                { key: 'spotify_monthly_listeners', label: 'Spotify Monthly Listeners', icon: Music },
                { key: 'spotify_popularity', label: 'Spotify Popularity Score', icon: TrendingUp },
                { key: 'youtube_subscribers', label: 'YouTube Subscribers', icon: Youtube },
              ].map(metric => (
                <div key={metric.key}>
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <metric.icon className="w-4 h-4" style={{ color: colors.secondary }} />
                      <span className="font-medium" style={{ color: colors.primary }}>{metric.label}</span>
                    </div>
                    <span className="text-lg font-bold" style={{ color: colors.primary }}>{(weights[metric.key] * 100).toFixed(0)}%</span>
                  </div>
                  <input
                    type="range"
                    min="0"
                    max="100"
                    value={weights[metric.key] * 100}
                    onChange={(e) => updateWeight(metric.key, parseInt(e.target.value) / 100)}
                    className="w-full h-2 rounded-full appearance-none cursor-pointer"
                    style={{ background: `linear-gradient(to right, ${colors.accent} 0%, ${colors.accent} ${weights[metric.key] * 100}%, ${colors.surface} ${weights[metric.key] * 100}%, ${colors.surface} 100%)` }}
                  />
                </div>
              ))}
            </div>
            <div className="flex justify-end mt-6 pt-6 border-t" style={{ borderColor: colors.border }}>
              <button 
                disabled={!isValid}
                className="px-6 py-2.5 rounded-xl font-medium text-white transition-all"
                style={{ backgroundColor: isValid ? colors.accent : colors.surface, color: isValid ? 'white' : colors.secondary, cursor: isValid ? 'pointer' : 'not-allowed' }}
              >
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}
      
      {activeTab === 'members' && (
        <div className="p-6 border rounded-2xl" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
          <div className="flex items-center justify-between mb-6">
            <h3 className="font-bold" style={{ color: colors.primary }}>Team Members</h3>
            <button className="px-4 py-2 rounded-xl font-medium text-white text-sm transition-all hover:opacity-90" style={{ backgroundColor: colors.accent }}>
              Invite Member
            </button>
          </div>
          <div className="space-y-3">
            {[
              { name: 'Alex Thompson', email: 'alex@example.com', role: 'owner', avatar: 'https://picsum.photos/seed/alex/40/40' },
              { name: 'Sam Rivera', email: 'sam@example.com', role: 'admin', avatar: 'https://picsum.photos/seed/sam/40/40' },
              { name: 'Jordan Lee', email: 'jordan@example.com', role: 'member', avatar: 'https://picsum.photos/seed/jordan/40/40' },
            ].map((member, i) => (
              <div key={i} className="flex items-center gap-4 p-4 rounded-xl" style={{ backgroundColor: colors.surface }}>
                <img src={member.avatar} alt={member.name} className="w-10 h-10 rounded-full" />
                <div className="flex-1">
                  <div className="font-semibold" style={{ color: colors.primary }}>{member.name}</div>
                  <div className="text-sm" style={{ color: colors.secondary }}>{member.email}</div>
                </div>
                <div 
                  className="px-3 py-1 rounded-full text-xs font-medium"
                  style={{ backgroundColor: member.role === 'owner' ? `${colors.accent}20` : colors.surface, color: member.role === 'owner' ? colors.accent : colors.secondary, border: `1px solid ${member.role === 'owner' ? colors.accent : colors.border}` }}
                >
                  {member.role.charAt(0).toUpperCase() + member.role.slice(1)}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

// Page: Lineups List
const LineupsPage = ({ onSelectLineup, onCreateLineup }) => {
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newLineupName, setNewLineupName] = useState('');
  const [newLineupDescription, setNewLineupDescription] = useState('');
  
  const getArtistCount = (lineup) => {
    return Object.values(lineup.artists).flat().length;
  };
  
  const getArtistsByTier = (lineup, tier) => {
    return lineup.artists[tier]?.map(id => mockArtists.find(a => a.id === id)).filter(Boolean) || [];
  };
  
  const handleCreate = () => {
    if (newLineupName.trim()) {
      onCreateLineup({
        id: Date.now(),
        name: newLineupName,
        description: newLineupDescription,
        updatedAt: 'Just now',
        createdAt: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        artists: {
          headliner: [],
          sub_headliner: [],
          mid_tier: [],
          undercard: []
        }
      });
      setShowCreateModal(false);
      setNewLineupName('');
      setNewLineupDescription('');
    }
  };
  
  return (
    <div className="min-h-screen">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-black mb-2" style={{ color: colors.primary }}>My Lineups</h1>
          <p style={{ color: colors.secondary }}>Manage your festival lineups and artist placements</p>
        </div>
        <button 
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-5 py-3 rounded-xl font-medium text-white transition-all hover:opacity-90"
          style={{ backgroundColor: colors.accent }}
        >
          <Plus className="w-5 h-5" />
          Create Lineup
        </button>
      </div>
      
      {/* Lineups Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {mockLineups.map(lineup => (
          <div 
            key={lineup.id}
            className="border rounded-2xl overflow-hidden hover:shadow-lg transition-all cursor-pointer"
            style={{ backgroundColor: colors.background, borderColor: colors.border }}
            onClick={() => onSelectLineup(lineup)}
          >
            {/* Lineup Header */}
            <div className="p-5 border-b" style={{ borderColor: colors.border }}>
              <div className="flex items-start justify-between mb-2">
                <div>
                  <h3 className="text-xl font-bold" style={{ color: colors.primary }}>{lineup.name}</h3>
                  <p className="text-sm" style={{ color: colors.secondary }}>{lineup.description}</p>
                </div>
                <div className="text-right">
                  <p className="text-2xl font-black" style={{ color: colors.primary }}>{getArtistCount(lineup)}</p>
                  <p className="text-xs" style={{ color: colors.secondary }}>artists</p>
                </div>
              </div>
              <div className="flex items-center gap-4 text-xs" style={{ color: colors.secondary }}>
                <span>Created {lineup.createdAt}</span>
                <span>•</span>
                <span>Updated {lineup.updatedAt}</span>
              </div>
            </div>
            
            {/* Booking Status Summary */}
            {lineup.artistStatuses && (
              <div className="px-5 py-3 border-b flex items-center gap-2 flex-wrap" style={{ borderColor: colors.border }}>
                {Object.entries(statusConfig).map(([statusId, config]) => {
                  const count = Object.values(lineup.artistStatuses).filter(s => s.status === statusId).length;
                  if (count === 0) return null;
                  const StatusIcon = config.icon;
                  return (
                    <div 
                      key={statusId}
                      className="flex items-center gap-1 px-2 py-1 rounded-full text-xs"
                      style={{ backgroundColor: config.bgColor, color: config.color }}
                    >
                      <StatusIcon className="w-3 h-3" />
                      <span>{count}</span>
                    </div>
                  );
                })}
              </div>
            )}
            
            {/* Tier Preview */}
            <div className="p-4 space-y-3">
              {Object.entries(tierConfig).map(([tierId, tier]) => {
                const tierArtists = getArtistsByTier(lineup, tierId);
                return (
                  <div key={tierId} className="flex items-center gap-3">
                    <div className="w-24 flex items-center gap-2">
                      <div className="w-2 h-2 rounded-full" style={{ backgroundColor: tier.color }} />
                      <span className="text-xs font-medium" style={{ color: tier.color }}>{tier.label}</span>
                    </div>
                    <div className="flex-1 flex items-center gap-1">
                      {tierArtists.length > 0 ? (
                        <>
                          <div className="flex -space-x-2">
                            {tierArtists.slice(0, 4).map(artist => (
                              <img 
                                key={artist.id}
                                src={artist.image} 
                                alt={artist.name}
                                className="w-7 h-7 rounded-full border-2 border-white object-cover"
                              />
                            ))}
                          </div>
                          {tierArtists.length > 4 && (
                            <span className="text-xs ml-2" style={{ color: colors.secondary }}>+{tierArtists.length - 4} more</span>
                          )}
                        </>
                      ) : (
                        <span className="text-xs" style={{ color: colors.secondary }}>No artists</span>
                      )}
                    </div>
                    <span className="text-xs font-medium" style={{ color: colors.secondary }}>{tierArtists.length}</span>
                  </div>
                );
              })}
            </div>
            
            {/* Footer */}
            <div className="px-5 py-3 flex items-center justify-between" style={{ backgroundColor: colors.surface }}>
              <span className="text-sm font-medium" style={{ color: colors.accent }}>View & Edit</span>
              <ChevronRight className="w-4 h-4" style={{ color: colors.accent }} />
            </div>
          </div>
        ))}
      </div>
      
      {/* Empty State */}
      {mockLineups.length === 0 && (
        <div className="text-center py-20">
          <div className="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center" style={{ backgroundColor: colors.surface }}>
            <List className="w-10 h-10" style={{ color: colors.secondary }} />
          </div>
          <h3 className="text-xl font-bold mb-2" style={{ color: colors.primary }}>No lineups yet</h3>
          <p className="mb-6" style={{ color: colors.secondary }}>Create your first lineup to start building your festival</p>
          <button 
            onClick={() => setShowCreateModal(true)}
            className="px-6 py-3 rounded-xl font-medium text-white"
            style={{ backgroundColor: colors.accent }}
          >
            Create Your First Lineup
          </button>
        </div>
      )}
      
      {/* Create Lineup Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowCreateModal(false)} />
          <div className="relative w-full max-w-md rounded-2xl overflow-hidden shadow-2xl p-6" style={{ backgroundColor: colors.background }}>
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-bold" style={{ color: colors.primary }}>Create New Lineup</h3>
              <button onClick={() => setShowCreateModal(false)} className="p-2 rounded-lg hover:bg-gray-100">
                <X className="w-5 h-5" style={{ color: colors.secondary }} />
              </button>
            </div>
            
            <div className="space-y-4 mb-6">
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: colors.primary }}>Lineup Name *</label>
                <input
                  type="text"
                  value={newLineupName}
                  onChange={(e) => setNewLineupName(e.target.value)}
                  placeholder="e.g., Summer Festival 2025"
                  className="w-full px-4 py-3 border rounded-xl focus:outline-none focus:ring-2"
                  style={{ borderColor: colors.border, color: colors.primary }}
                  autoFocus
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2" style={{ color: colors.primary }}>Description (optional)</label>
                <textarea
                  value={newLineupDescription}
                  onChange={(e) => setNewLineupDescription(e.target.value)}
                  placeholder="Brief description of this lineup..."
                  rows={3}
                  className="w-full px-4 py-3 border rounded-xl focus:outline-none focus:ring-2 resize-none"
                  style={{ borderColor: colors.border, color: colors.primary }}
                />
              </div>
            </div>
            
            {/* Tier Info */}
            <div className="p-4 rounded-xl mb-6" style={{ backgroundColor: colors.surface }}>
              <p className="text-sm font-medium mb-3" style={{ color: colors.primary }}>Your lineup will have these tiers:</p>
              <div className="grid grid-cols-2 gap-2">
                {Object.entries(tierConfig).map(([tierId, tier]) => (
                  <div key={tierId} className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full" style={{ backgroundColor: tier.color }} />
                    <span className="text-xs" style={{ color: colors.secondary }}>{tier.label}</span>
                  </div>
                ))}
              </div>
            </div>
            
            <div className="flex gap-3">
              <button 
                onClick={() => setShowCreateModal(false)}
                className="flex-1 py-3 rounded-xl font-medium transition-colors"
                style={{ backgroundColor: colors.surface, color: colors.primary }}
              >
                Cancel
              </button>
              <button 
                onClick={handleCreate}
                disabled={!newLineupName.trim()}
                className="flex-1 py-3 rounded-xl font-medium text-white transition-all"
                style={{ 
                  backgroundColor: newLineupName.trim() ? colors.accent : colors.border,
                  cursor: newLineupName.trim() ? 'pointer' : 'not-allowed'
                }}
              >
                Create Lineup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// Page: Lineup Detail/Builder
const LineupDetail = ({ lineup, onBack, onSelectArtist }) => {
  const [lineupData, setLineupData] = useState(lineup);
  const [isEditingName, setIsEditingName] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('lineup');
  const [artistStatuses, setArtistStatuses] = useState(lineup.artistStatuses || {});
  const [selectedArtistForStatus, setSelectedArtistForStatus] = useState(null);
  const [statusFilter, setStatusFilter] = useState('all');
  const [compareMode, setCompareMode] = useState(false);
  const [compareArtists, setCompareArtists] = useState([]);
  const [showCompareModal, setShowCompareModal] = useState(false);
  // Stacked artists: { stackId: { primary: artistId, alternatives: [artistId, ...], tier: 'headliner' } }
  const [artistStacks, setArtistStacks] = useState(lineup.artistStacks || {});
  const [stackMode, setStackMode] = useState(false);
  const [stackingArtist, setStackingArtist] = useState(null); // The artist we're adding alternatives to
  const [showStackModal, setShowStackModal] = useState(null); // stackId to show modal for
  const [schedule, setSchedule] = useState(() => {
    // Initialize schedule with default times based on tier
    const defaultSchedule = {};
    const tierTimes = {
      headliner: { day: 'Saturday', startHour: 21, duration: 90 },
      sub_headliner: { day: 'Saturday', startHour: 18, duration: 60 },
      mid_tier: { day: 'Saturday', startHour: 14, duration: 45 },
      undercard: { day: 'Saturday', startHour: 12, duration: 30 },
    };
    
    Object.entries(lineup.artists).forEach(([tier, artistIds]) => {
      const tierConfig = tierTimes[tier];
      artistIds.forEach((id, index) => {
        const startHour = tierConfig.startHour + (index * (tierConfig.duration / 60 + 0.5));
        defaultSchedule[id] = {
          day: index % 2 === 0 ? 'Saturday' : 'Sunday',
          stage: tier === 'headliner' || tier === 'sub_headliner' ? 'Main Stage' : tier === 'mid_tier' ? 'Second Stage' : 'Tent Stage',
          startTime: `${Math.floor(startHour % 24)}:${startHour % 1 === 0 ? '00' : '30'}`,
          duration: tierConfig.duration,
        };
      });
    });
    return defaultSchedule;
  });
  const [selectedDay, setSelectedDay] = useState('Saturday');
  const [editingSlot, setEditingSlot] = useState(null);
  
  const stages = ['Main Stage', 'Second Stage', 'Tent Stage', 'DJ Booth'];
  const days = ['Friday', 'Saturday', 'Sunday'];
  const hours = Array.from({ length: 15 }, (_, i) => i + 10); // 10:00 to 24:00
  
  const getArtistsByTier = (tier) => {
    return lineupData.artists[tier]?.map(id => mockArtists.find(a => a.id === id)).filter(Boolean) || [];
  };
  
  const getAllLineupArtistIds = () => {
    return Object.values(lineupData.artists).flat();
  };
  
  const getTotalArtistCount = () => {
    return getAllLineupArtistIds().length;
  };
  
  const getAllArtists = () => {
    return getAllLineupArtistIds().map(id => mockArtists.find(a => a.id === id)).filter(Boolean);
  };
  
  const addArtistToTier = (artistId, tier) => {
    setLineupData(prev => ({
      ...prev,
      artists: {
        ...prev.artists,
        [tier]: [...prev.artists[tier], artistId]
      }
    }));
    // Add default schedule
    const tierDefaults = {
      headliner: { stage: 'Main Stage', duration: 90 },
      sub_headliner: { stage: 'Main Stage', duration: 60 },
      mid_tier: { stage: 'Second Stage', duration: 45 },
      undercard: { stage: 'Tent Stage', duration: 30 },
    };
    setSchedule(prev => ({
      ...prev,
      [artistId]: {
        day: 'Saturday',
        stage: tierDefaults[tier].stage,
        startTime: '14:00',
        duration: tierDefaults[tier].duration,
      }
    }));
    // Add default status
    setArtistStatuses(prev => ({
      ...prev,
      [artistId]: {
        status: 'idea',
        fee: null,
        notes: '',
        contactEmail: ''
      }
    }));
    setSearchOpen(false);
    setSearchQuery('');
  };
  
  const removeArtist = (artistId, tier) => {
    setLineupData(prev => ({
      ...prev,
      artists: {
        ...prev.artists,
        [tier]: prev.artists[tier].filter(id => id !== artistId)
      }
    }));
    setSchedule(prev => {
      const newSchedule = { ...prev };
      delete newSchedule[artistId];
      return newSchedule;
    });
    setArtistStatuses(prev => {
      const newStatuses = { ...prev };
      delete newStatuses[artistId];
      return newStatuses;
    });
  };
  
  const updateArtistStatus = (artistId, field, value) => {
    setArtistStatuses(prev => ({
      ...prev,
      [artistId]: {
        ...prev[artistId],
        [field]: value
      }
    }));
  };
  
  const getStatusCounts = () => {
    const counts = {};
    Object.values(artistStatuses).forEach(({ status }) => {
      counts[status] = (counts[status] || 0) + 1;
    });
    return counts;
  };
  
  const toggleCompareArtist = (artistId) => {
    setCompareArtists(prev => {
      if (prev.includes(artistId)) {
        return prev.filter(id => id !== artistId);
      }
      if (prev.length >= 4) {
        return prev; // Max 4 artists
      }
      return [...prev, artistId];
    });
  };
  
  const getCompareArtists = () => {
    return compareArtists.map(id => mockArtists.find(a => a.id === id)).filter(Boolean);
  };
  
  const formatNumber = (num) => {
    if (num >= 1000000000) return (num / 1000000000).toFixed(1) + 'B';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num?.toString() || '0';
  };
  
  const getHighestValue = (artists, key) => {
    return Math.max(...artists.map(a => a[key] || 0));
  };
  
  // Stacking functions
  const createStack = (primaryArtistId, tier) => {
    const stackId = `stack_${Date.now()}`;
    setArtistStacks(prev => ({
      ...prev,
      [stackId]: {
        primary: primaryArtistId,
        alternatives: [],
        tier: tier
      }
    }));
    setStackingArtist({ stackId, artistId: primaryArtistId, tier });
  };
  
  const addToStack = (stackId, artistId) => {
    setArtistStacks(prev => ({
      ...prev,
      [stackId]: {
        ...prev[stackId],
        alternatives: [...prev[stackId].alternatives, artistId]
      }
    }));
    // Add artist to lineup if not already there
    const tier = artistStacks[stackId]?.tier;
    if (tier && !lineupData.artists[tier].includes(artistId)) {
      setLineupData(prev => ({
        ...prev,
        artists: {
          ...prev.artists,
          [tier]: [...prev.artists[tier], artistId]
        }
      }));
      // Set default status
      setArtistStatuses(prev => ({
        ...prev,
        [artistId]: { status: 'idea', fee: null, notes: 'Alternative option', contactEmail: '' }
      }));
    }
  };
  
  const removeFromStack = (stackId, artistId) => {
    setArtistStacks(prev => ({
      ...prev,
      [stackId]: {
        ...prev[stackId],
        alternatives: prev[stackId].alternatives.filter(id => id !== artistId)
      }
    }));
  };
  
  const promoteAlternative = (stackId, artistId) => {
    // Swap the primary with this alternative
    setArtistStacks(prev => {
      const stack = prev[stackId];
      const oldPrimary = stack.primary;
      return {
        ...prev,
        [stackId]: {
          ...stack,
          primary: artistId,
          alternatives: [oldPrimary, ...stack.alternatives.filter(id => id !== artistId)]
        }
      };
    });
  };
  
  const dissolveStack = (stackId) => {
    const stack = artistStacks[stackId];
    if (stack) {
      // Remove all alternatives from the lineup
      stack.alternatives.forEach(altId => {
        const tier = stack.tier;
        setLineupData(prev => ({
          ...prev,
          artists: {
            ...prev.artists,
            [tier]: prev.artists[tier].filter(id => id !== altId)
          }
        }));
      });
    }
    setArtistStacks(prev => {
      const newStacks = { ...prev };
      delete newStacks[stackId];
      return newStacks;
    });
  };
  
  const getArtistStack = (artistId) => {
    for (const [stackId, stack] of Object.entries(artistStacks)) {
      if (stack.primary === artistId || stack.alternatives.includes(artistId)) {
        return { stackId, ...stack, isAlternative: stack.alternatives.includes(artistId) };
      }
    }
    return null;
  };
  
  const isArtistInAnyStack = (artistId) => {
    return Object.values(artistStacks).some(
      stack => stack.primary === artistId || stack.alternatives.includes(artistId)
    );
  };
  
  const updateSchedule = (artistId, field, value) => {
    setSchedule(prev => ({
      ...prev,
      [artistId]: {
        ...prev[artistId],
        [field]: value
      }
    }));
  };
  
  const availableArtists = mockArtists.filter(a => 
    !getAllLineupArtistIds().includes(a.id) &&
    (searchQuery === '' || a.name.toLowerCase().includes(searchQuery.toLowerCase()))
  );
  
  const getScheduleForDay = (day) => {
    return getAllArtists()
      .filter(artist => schedule[artist.id]?.day === day)
      .map(artist => ({
        ...artist,
        schedule: schedule[artist.id]
      }))
      .sort((a, b) => {
        const timeA = a.schedule.startTime.split(':').map(Number);
        const timeB = b.schedule.startTime.split(':').map(Number);
        return (timeA[0] * 60 + timeA[1]) - (timeB[0] * 60 + timeB[1]);
      });
  };
  
  const getTimePosition = (timeStr) => {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return ((hours - 10) * 60 + minutes) / (15 * 60) * 100;
  };
  
  const getSlotWidth = (duration) => {
    return (duration / (15 * 60)) * 100;
  };
  
  const getArtistTier = (artistId) => {
    for (const [tier, ids] of Object.entries(lineupData.artists)) {
      if (ids.includes(artistId)) return tier;
    }
    return null;
  };
  
  return (
    <div className="min-h-screen">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <button 
            onClick={onBack}
            className="p-2 rounded-xl transition-colors hover:bg-gray-100"
            style={{ backgroundColor: colors.surface }}
          >
            <ChevronLeft className="w-5 h-5" style={{ color: colors.primary }} />
          </button>
          <div>
            {isEditingName ? (
              <input
                type="text"
                value={lineupData.name}
                onChange={(e) => setLineupData(prev => ({ ...prev, name: e.target.value }))}
                onBlur={() => setIsEditingName(false)}
                onKeyDown={(e) => e.key === 'Enter' && setIsEditingName(false)}
                className="text-2xl font-black bg-transparent border-b-2 focus:outline-none"
                style={{ color: colors.primary, borderColor: colors.accent }}
                autoFocus
              />
            ) : (
              <h1 
                className="text-2xl font-black cursor-pointer hover:opacity-70"
                style={{ color: colors.primary }}
                onClick={() => setIsEditingName(true)}
              >
                {lineupData.name}
              </h1>
            )}
            <p className="text-sm" style={{ color: colors.secondary }}>
              {getTotalArtistCount()} artists • Updated {lineupData.updatedAt}
            </p>
          </div>
        </div>
        
        <div className="flex items-center gap-3">
          <button 
            className="px-4 py-2 rounded-xl font-medium text-white transition-all hover:opacity-90"
            style={{ backgroundColor: colors.accent }}
          >
            Export
          </button>
        </div>
      </div>
      
      {/* Full-width Tabs Bar */}
      <div 
        className="-mx-8 px-8 border-b shadow-sm"
        style={{ backgroundColor: colors.background, borderColor: colors.border }}
      >
        <div className="max-w-7xl mx-auto">
          <div className="flex">
            {[
              { id: 'lineup', label: 'Lineup', icon: Users },
              { id: 'booking', label: 'Booking', icon: FileText },
              { id: 'schedule', label: 'Schedule', icon: Clock },
            ].map(tab => (
              <button
                key={tab.id}
                onClick={() => {
                  setActiveTab(tab.id);
                  if (tab.id !== 'lineup') {
                    setStackMode(false);
                    setStackingArtist(null);
                    setCompareMode(false);
                    setCompareArtists([]);
                  }
                }}
                className="flex items-center gap-2 px-6 py-4 font-medium transition-all border-b-2 -mb-px"
                style={{
                  borderColor: activeTab === tab.id ? colors.accent : 'transparent',
                  color: activeTab === tab.id ? colors.primary : colors.secondary,
                  backgroundColor: 'transparent',
                }}
              >
                <tab.icon className="w-4 h-4" />
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>
      
      {/* Content Area with different background */}
      <div 
        className="-mx-8 px-8 pt-6 pb-8 min-h-screen"
        style={{ backgroundColor: colors.surface }}
      >
        <div className="max-w-7xl mx-auto">
      
      {activeTab === 'lineup' && (
        <>
          {/* Stack Mode Banner */}
          {stackMode && (
            <div 
              className="mb-4 p-4 rounded-2xl border-2 flex items-center justify-between"
              style={{ backgroundColor: '#f3e8ff', borderColor: '#8b5cf6' }}
            >
              <div className="flex items-center gap-3">
                <Layers className="w-5 h-5" style={{ color: '#8b5cf6' }} />
                <div>
                  <p className="font-medium" style={{ color: colors.primary }}>
                    {stackingArtist 
                      ? `Adding alternatives to: ${mockArtists.find(a => a.id === stackingArtist.artistId)?.name}`
                      : 'Stack Mode: Group alternative artists together'
                    }
                  </p>
                  <p className="text-sm" style={{ color: colors.secondary }}>
                    {stackingArtist 
                      ? 'Click on other artists to add them as alternatives for this slot'
                      : 'Click "Stack" on any artist card to start creating an alternatives group'
                    }
                  </p>
                </div>
              </div>
              {stackingArtist && (
                <button
                  onClick={() => setStackingArtist(null)}
                  className="px-4 py-1.5 rounded-lg text-sm font-medium"
                  style={{ backgroundColor: 'white', color: '#8b5cf6' }}
                >
                  Done Adding
                </button>
              )}
            </div>
          )}
          
          {/* Compare Mode Banner */}
          {compareMode && (
            <div 
              className="mb-4 p-4 rounded-2xl border-2 flex items-center justify-between"
              style={{ backgroundColor: `${colors.accent}10`, borderColor: colors.accent }}
            >
              <div className="flex items-center gap-3">
                <BarChart3 className="w-5 h-5" style={{ color: colors.accent }} />
                <div>
                  <p className="font-medium" style={{ color: colors.primary }}>
                    Compare Mode: Select up to 4 artists
                  </p>
                  <p className="text-sm" style={{ color: colors.secondary }}>
                    {compareArtists.length === 0 
                      ? 'Click on artist cards to select them for comparison'
                      : `${compareArtists.length} artist${compareArtists.length !== 1 ? 's' : ''} selected`
                    }
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                {compareArtists.length > 0 && (
                  <div className="flex -space-x-2 mr-2">
                    {getCompareArtists().map(artist => (
                      <img 
                        key={artist.id}
                        src={artist.image}
                        alt={artist.name}
                        className="w-8 h-8 rounded-full border-2 border-white object-cover"
                      />
                    ))}
                  </div>
                )}
                <button
                  onClick={() => setCompareArtists([])}
                  className="px-3 py-1.5 rounded-lg text-sm font-medium"
                  style={{ backgroundColor: colors.surface, color: colors.secondary }}
                  disabled={compareArtists.length === 0}
                >
                  Clear
                </button>
                <button
                  onClick={() => setShowCompareModal(true)}
                  disabled={compareArtists.length < 2}
                  className="px-4 py-1.5 rounded-lg text-sm font-medium text-white transition-all"
                  style={{ 
                    backgroundColor: compareArtists.length >= 2 ? colors.accent : colors.border,
                    cursor: compareArtists.length >= 2 ? 'pointer' : 'not-allowed'
                  }}
                >
                  Compare {compareArtists.length >= 2 ? `(${compareArtists.length})` : ''}
                </button>
              </div>
            </div>
          )}
        
          {/* Combined Toolbar: Search + Actions */}
          <div className="relative mb-8">
            <div 
              className="flex items-center gap-3 p-3 border rounded-2xl"
              style={{ backgroundColor: colors.background, borderColor: colors.border }}
            >
              {/* Search Section */}
              <div 
                className="flex-1 flex items-center gap-3 px-3 py-2 rounded-xl cursor-pointer transition-colors hover:bg-gray-50"
                style={{ backgroundColor: colors.surface }}
                onClick={() => setSearchOpen(true)}
              >
                <Search className="w-5 h-5" style={{ color: colors.secondary }} />
                <span style={{ color: colors.secondary }}>Search and add artists...</span>
              </div>
              
              {/* Divider */}
              <div className="w-px h-8" style={{ backgroundColor: colors.border }} />
              
              {/* Action Buttons */}
              <button 
                onClick={() => {
                  setStackMode(!stackMode);
                  if (stackMode) {
                    setStackingArtist(null);
                  }
                  setCompareMode(false);
                  setCompareArtists([]);
                }}
                className="px-4 py-2 rounded-xl font-medium transition-colors flex items-center gap-2"
                style={{ 
                  backgroundColor: stackMode ? '#8b5cf6' : colors.surface, 
                  color: stackMode ? 'white' : colors.primary 
                }}
              >
                <Layers className="w-4 h-4" />
                {stackMode ? 'Exit Stack' : 'Stack'}
              </button>
              <button 
                onClick={() => {
                  setCompareMode(!compareMode);
                  if (compareMode) {
                    setCompareArtists([]);
                  }
                  setStackMode(false);
                  setStackingArtist(null);
                }}
                className="px-4 py-2 rounded-xl font-medium transition-colors flex items-center gap-2"
                style={{ 
                  backgroundColor: compareMode ? colors.accent : colors.surface, 
                  color: compareMode ? 'white' : colors.primary 
                }}
              >
                <BarChart3 className="w-4 h-4" />
                {compareMode ? 'Exit Compare' : 'Compare'}
              </button>
            </div>
            
            {searchOpen && (
              <div 
                className="absolute inset-x-0 top-0 z-50 border rounded-2xl overflow-hidden shadow-xl"
                style={{ backgroundColor: colors.background, borderColor: colors.border }}
              >
                <div className="flex items-center gap-3 p-4 border-b" style={{ borderColor: colors.border }}>
                  <Search className="w-5 h-5" style={{ color: colors.secondary }} />
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search artists..."
                    className="flex-1 bg-transparent focus:outline-none"
                    style={{ color: colors.primary }}
                    autoFocus
                  />
                  <button 
                    onClick={() => { setSearchOpen(false); setSearchQuery(''); }} 
                    className="p-1 rounded-lg transition-colors hover:bg-gray-100"
                  >
                    <X className="w-5 h-5" style={{ color: colors.primary }} />
                  </button>
                </div>
                <div className="max-h-80 overflow-y-auto">
                  {availableArtists.length > 0 ? (
                    availableArtists.map(artist => (
                      <div 
                        key={artist.id}
                        className="flex items-center gap-4 p-4 border-b last:border-0 hover:bg-gray-50"
                        style={{ borderColor: colors.border }}
                      >
                        <img src={artist.image} alt={artist.name} className="w-10 h-10 rounded-lg object-cover" />
                        <div className="flex-1">
                          <div className="font-semibold" style={{ color: colors.primary }}>{artist.name}</div>
                          <div className="text-xs" style={{ color: colors.secondary }}>
                            Suggested: {tierConfig[artist.tierSuggestion].label}
                          </div>
                        </div>
                        <ScoreBadge score={artist.score} />
                        <div className="flex gap-1">
                          {Object.entries(tierConfig).map(([tierId, tier]) => (
                            <button
                              key={tierId}
                              onClick={() => addArtistToTier(artist.id, tierId)}
                              className="px-2 py-1 text-xs rounded-lg transition-colors hover:opacity-80"
                              style={{ backgroundColor: tier.bgColor, color: tier.color }}
                              title={`Add to ${tier.label}`}
                            >
                              {tier.label.charAt(0)}
                            </button>
                          ))}
                        </div>
                      </div>
                    ))
                  ) : (
                    <div className="p-8 text-center" style={{ color: colors.secondary }}>
                      {searchQuery ? 'No matching artists found' : 'All artists have been added'}
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
          
          {/* Tier Sections */}
          <div className="space-y-6">
            {Object.entries(tierConfig).map(([tierId, tier]) => {
              const tierArtists = getArtistsByTier(tierId);
              return (
                <div 
                  key={tierId} 
                  className="rounded-2xl border overflow-hidden"
                  style={{ backgroundColor: tier.bgColor, borderColor: colors.border }}
                >
                  {/* Tier Header */}
                  <div className="flex items-center justify-between p-4 border-b" style={{ borderColor: colors.border }}>
                    <div className="flex items-center gap-3">
                      <div className="w-3 h-3 rounded-full" style={{ backgroundColor: tier.color }} />
                      <span className="font-black tracking-wider text-sm" style={{ color: tier.color }}>
                        {tier.label}
                      </span>
                      <span className="text-xs ml-2" style={{ color: colors.secondary }}>
                        {tierArtists.length} artists
                      </span>
                    </div>
                    <div className="text-xs" style={{ color: colors.secondary }}>
                      {tierId === 'headliner' && 'Top billing • Main stage closers'}
                      {tierId === 'sub_headliner' && 'Second billing • Prime slots'}
                      {tierId === 'mid_tier' && 'Supporting acts • Afternoon slots'}
                      {tierId === 'undercard' && 'Opening acts • Early slots'}
                    </div>
                  </div>
                  
                  {/* Artists Grid */}
                  <div className="p-4">
                    {tierArtists.length > 0 ? (
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        {tierArtists.map(artist => {
                          const isSelected = compareArtists.includes(artist.id);
                          const stack = getArtistStack(artist.id);
                          const isInStack = !!stack;
                          const isPrimaryInStack = stack && stack.primary === artist.id;
                          const isAlternative = stack && stack.isAlternative;
                          
                          // If this is an alternative, don't render separately - it will be shown under its primary
                          if (isAlternative) return null;
                          
                          // Get alternatives if this is a primary
                          const alternatives = isPrimaryInStack 
                            ? stack.alternatives.map(id => mockArtists.find(a => a.id === id)).filter(Boolean)
                            : [];
                          
                          return (
                            <div key={artist.id} className="space-y-2">
                              {/* Primary Artist Card */}
                              <div 
                                onClick={() => {
                                  if (compareMode) {
                                    toggleCompareArtist(artist.id);
                                  } else if (stackMode && stackingArtist && stackingArtist.artistId !== artist.id) {
                                    addToStack(stackingArtist.stackId, artist.id);
                                  }
                                }}
                                className={`group relative flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 hover:shadow-md ${compareMode || (stackMode && stackingArtist) ? 'cursor-pointer' : ''}`}
                                style={{ 
                                  backgroundColor: isSelected ? `${colors.accent}10` : isPrimaryInStack ? '#f3e8ff' : colors.background, 
                                  borderColor: isSelected ? colors.accent : isPrimaryInStack ? '#8b5cf6' : colors.border,
                                  borderWidth: isSelected || isPrimaryInStack ? '2px' : '1px'
                                }}
                              >
                                {/* Stack indicator */}
                                {isPrimaryInStack && (
                                  <div 
                                    className="absolute -top-2 -left-2 w-6 h-6 rounded-full flex items-center justify-center"
                                    style={{ backgroundColor: '#8b5cf6', color: 'white' }}
                                  >
                                    <Layers className="w-3 h-3" />
                                  </div>
                                )}
                                
                                {compareMode && (
                                  <div 
                                    className={`absolute -top-2 -right-2 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold transition-all ${isSelected ? 'scale-100' : 'scale-0'}`}
                                    style={{ backgroundColor: colors.accent, color: 'white' }}
                                  >
                                    {compareArtists.indexOf(artist.id) + 1}
                                  </div>
                                )}
                                {!compareMode && !stackMode && (
                                  <div className="cursor-grab active:cursor-grabbing p-1 -ml-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <GripVertical className="w-4 h-4" style={{ color: colors.secondary }} />
                                  </div>
                                )}
                                {compareMode && (
                                  <div 
                                    className="w-5 h-5 rounded border-2 flex items-center justify-center transition-all"
                                    style={{ 
                                      borderColor: isSelected ? colors.accent : colors.border,
                                      backgroundColor: isSelected ? colors.accent : 'transparent'
                                    }}
                                  >
                                    {isSelected && <Check className="w-3 h-3 text-white" />}
                                  </div>
                                )}
                                <img 
                                  src={artist.image} 
                                  alt={artist.name} 
                                  className="w-10 h-10 rounded-lg object-cover cursor-pointer"
                                  onClick={(e) => {
                                    if (!compareMode && !stackMode) {
                                      e.stopPropagation();
                                      onSelectArtist(artist);
                                    }
                                  }}
                                />
                                <div className="flex-1 min-w-0">
                                  <div className="flex items-center gap-2">
                                    <span 
                                      className="font-semibold text-sm truncate cursor-pointer hover:underline" 
                                      style={{ color: colors.primary }}
                                      onClick={(e) => {
                                        if (!compareMode && !stackMode) {
                                          e.stopPropagation();
                                          onSelectArtist(artist);
                                        }
                                      }}
                                    >
                                      {artist.name}
                                    </span>
                                    {artistStatuses[artist.id] && (
                                      <span 
                                        className="flex items-center justify-center w-5 h-5 rounded"
                                        style={{ 
                                          backgroundColor: statusConfig[artistStatuses[artist.id].status]?.bgColor,
                                          color: statusConfig[artistStatuses[artist.id].status]?.color
                                        }}
                                      >
                                        {React.createElement(statusConfig[artistStatuses[artist.id].status]?.icon, { className: 'w-3 h-3' })}
                                      </span>
                                    )}
                                  </div>
                                  <div className="flex items-center gap-2">
                                    <ScoreBadge score={artist.score} />
                                    {artist.tierSuggestion !== tierId && (
                                      <span className="text-xs flex items-center gap-1" style={{ color: colors.accent }}>
                                        <Sparkles className="w-3 h-3" />
                                      </span>
                                    )}
                                  </div>
                                </div>
                                
                                {/* Action buttons */}
                                {!compareMode && !stackMode && (
                                  <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    {!isInStack && (
                                      <button 
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          createStack(artist.id, tierId);
                                          setStackMode(true);
                                        }}
                                        className="p-1.5 rounded-lg transition-all hover:bg-purple-50"
                                        style={{ color: '#8b5cf6' }}
                                        title="Create alternatives stack"
                                      >
                                        <Layers className="w-4 h-4" />
                                      </button>
                                    )}
                                    {isPrimaryInStack && (
                                      <button 
                                        onClick={(e) => {
                                          e.stopPropagation();
                                          setShowStackModal(stack.stackId);
                                        }}
                                        className="p-1.5 rounded-lg transition-all hover:bg-purple-50"
                                        style={{ color: '#8b5cf6' }}
                                        title="Manage stack"
                                      >
                                        <MoreHorizontal className="w-4 h-4" />
                                      </button>
                                    )}
                                    <button 
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        if (isPrimaryInStack) {
                                          dissolveStack(stack.stackId);
                                        }
                                        removeArtist(artist.id, tierId);
                                      }}
                                      className="p-1.5 rounded-lg transition-all hover:bg-red-50 hover:text-red-500"
                                      style={{ color: colors.secondary }}
                                    >
                                      <X className="w-4 h-4" />
                                    </button>
                                  </div>
                                )}
                                
                                {stackMode && !stackingArtist && !isInStack && (
                                  <button 
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      createStack(artist.id, tierId);
                                    }}
                                    className="px-2 py-1 rounded-lg text-xs font-medium"
                                    style={{ backgroundColor: '#8b5cf6', color: 'white' }}
                                  >
                                    Stack
                                  </button>
                                )}
                              </div>
                              
                              {/* Alternatives (stacked under primary) */}
                              {alternatives.length > 0 && (
                                <div className="ml-4 pl-4 border-l-2 space-y-2" style={{ borderColor: '#8b5cf6' }}>
                                  <div className="text-xs font-medium flex items-center gap-1" style={{ color: '#8b5cf6' }}>
                                    <span>Alternatives ({alternatives.length})</span>
                                  </div>
                                  {alternatives.map(alt => (
                                    <div 
                                      key={alt.id}
                                      className="group flex items-center gap-2 p-2 rounded-lg border bg-white transition-all hover:shadow-sm"
                                      style={{ borderColor: colors.border }}
                                    >
                                      <img 
                                        src={alt.image} 
                                        alt={alt.name}
                                        className="w-8 h-8 rounded-lg object-cover cursor-pointer"
                                        onClick={() => onSelectArtist(alt)}
                                      />
                                      <div className="flex-1 min-w-0">
                                        <div 
                                          className="font-medium text-sm truncate cursor-pointer hover:underline"
                                          style={{ color: colors.primary }}
                                          onClick={() => onSelectArtist(alt)}
                                        >
                                          {alt.name}
                                        </div>
                                        <div className="flex items-center gap-2">
                                          <span className="text-xs" style={{ color: colors.secondary }}>
                                            Score: {alt.score}
                                          </span>
                                          {artistStatuses[alt.id] && (
                                            <span 
                                              className="text-xs flex items-center gap-1 px-1 py-0.5 rounded"
                                              style={{ 
                                                backgroundColor: statusConfig[artistStatuses[alt.id].status]?.bgColor,
                                                color: statusConfig[artistStatuses[alt.id].status]?.color
                                              }}
                                            >
                                              {React.createElement(statusConfig[artistStatuses[alt.id].status]?.icon, { className: 'w-2.5 h-2.5' })}
                                            </span>
                                          )}
                                        </div>
                                      </div>
                                      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button
                                          onClick={() => promoteAlternative(stack.stackId, alt.id)}
                                          className="p-1 rounded hover:bg-green-50"
                                          style={{ color: '#059669' }}
                                          title="Make primary"
                                        >
                                          <ArrowUp className="w-3 h-3" />
                                        </button>
                                        <button
                                          onClick={() => {
                                            removeFromStack(stack.stackId, alt.id);
                                            removeArtist(alt.id, tierId);
                                          }}
                                          className="p-1 rounded hover:bg-red-50 hover:text-red-500"
                                          style={{ color: colors.secondary }}
                                        >
                                          <X className="w-3 h-3" />
                                        </button>
                                      </div>
                                    </div>
                                  ))}
                                  {stackMode && stackingArtist?.stackId === stack.stackId && (
                                    <div 
                                      className="p-2 rounded-lg border-2 border-dashed text-center text-xs"
                                      style={{ borderColor: '#8b5cf6', color: '#8b5cf6' }}
                                    >
                                      Click another artist to add as alternative
                                    </div>
                                  )}
                                </div>
                              )}
                              
                              {/* Empty alternatives placeholder when stacking */}
                              {isPrimaryInStack && alternatives.length === 0 && stackMode && (
                                <div className="ml-4 pl-4 border-l-2" style={{ borderColor: '#8b5cf6' }}>
                                  <div 
                                    className="p-3 rounded-lg border-2 border-dashed text-center text-xs"
                                    style={{ borderColor: '#8b5cf6', color: '#8b5cf6' }}
                                  >
                                    Click another artist to add as alternative
                                  </div>
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    ) : (
                      <div 
                        className="text-center py-8 border-2 border-dashed rounded-xl"
                        style={{ borderColor: colors.border, color: colors.secondary }}
                      >
                        <p className="text-sm mb-2">No {tier.label.toLowerCase()} artists yet</p>
                        <p className="text-xs">Add artists from the search above</p>
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </>
      )}
      
      {activeTab === 'booking' && (
        <div>
          {/* Budget Summary Bar */}
          <div className="flex items-center gap-6 p-4 mb-6 rounded-2xl border" style={{ backgroundColor: colors.surface, borderColor: colors.border }}>
            <div className="flex items-center gap-2">
              <DollarSign className="w-5 h-5" style={{ color: colors.secondary }} />
              <span className="text-sm" style={{ color: colors.secondary }}>Budget:</span>
            </div>
            <div className="flex items-center gap-1">
              <span className="text-sm" style={{ color: colors.secondary }}>Confirmed</span>
              <span className="font-bold" style={{ color: statusConfig.confirmed.color }}>
                ${Object.entries(artistStatuses)
                  .filter(([_, s]) => s.status === 'confirmed' && s.fee)
                  .reduce((sum, [_, s]) => sum + s.fee, 0)
                  .toLocaleString()}
              </span>
            </div>
            <div className="w-px h-6" style={{ backgroundColor: colors.border }} />
            <div className="flex items-center gap-1">
              <span className="text-sm" style={{ color: colors.secondary }}>Pending</span>
              <span className="font-bold" style={{ color: statusConfig.contract_sent.color }}>
                ${Object.entries(artistStatuses)
                  .filter(([_, s]) => ['contract_sent', 'contract_signed', 'negotiating'].includes(s.status) && s.fee)
                  .reduce((sum, [_, s]) => sum + s.fee, 0)
                  .toLocaleString()}
              </span>
            </div>
            <div className="w-px h-6" style={{ backgroundColor: colors.border }} />
            <div className="flex items-center gap-1">
              <span className="text-sm" style={{ color: colors.secondary }}>Total Projected</span>
              <span className="font-bold" style={{ color: colors.primary }}>
                ${Object.entries(artistStatuses)
                  .filter(([_, s]) => s.status !== 'declined' && s.fee)
                  .reduce((sum, [_, s]) => sum + s.fee, 0)
                  .toLocaleString()}
              </span>
            </div>
          </div>
          
          {/* Kanban Board */}
          <div className="flex gap-4 overflow-x-auto pb-4" style={{ minHeight: '600px' }}>
            {Object.entries(statusConfig).map(([statusId, config]) => {
              const StatusIcon = config.icon;
              const columnArtists = getAllArtists().filter(
                artist => (artistStatuses[artist.id]?.status || 'idea') === statusId
              );
              
              return (
                <div 
                  key={statusId}
                  className="flex-shrink-0 w-72 flex flex-col rounded-2xl border"
                  style={{ backgroundColor: colors.surface, borderColor: colors.border }}
                  onDragOver={(e) => {
                    e.preventDefault();
                    e.currentTarget.style.backgroundColor = config.bgColor;
                  }}
                  onDragLeave={(e) => {
                    e.currentTarget.style.backgroundColor = colors.surface;
                  }}
                  onDrop={(e) => {
                    e.preventDefault();
                    e.currentTarget.style.backgroundColor = colors.surface;
                    const artistId = parseInt(e.dataTransfer.getData('artistId'));
                    if (artistId) {
                      updateArtistStatus(artistId, 'status', statusId);
                    }
                  }}
                >
                  {/* Column Header */}
                  <div 
                    className="p-4 border-b flex items-center justify-between"
                    style={{ borderColor: colors.border }}
                  >
                    <div className="flex items-center gap-2">
                      <div 
                        className="p-1.5 rounded-lg"
                        style={{ backgroundColor: config.bgColor }}
                      >
                        <StatusIcon className="w-4 h-4" style={{ color: config.color }} />
                      </div>
                      <span className="font-bold text-sm" style={{ color: colors.primary }}>
                        {config.label}
                      </span>
                    </div>
                    <span 
                      className="text-xs font-medium px-2 py-1 rounded-full"
                      style={{ backgroundColor: config.bgColor, color: config.color }}
                    >
                      {columnArtists.length}
                    </span>
                  </div>
                  
                  {/* Column Content */}
                  <div className="flex-1 p-3 space-y-3 overflow-y-auto">
                    {columnArtists.map(artist => {
                      const status = artistStatuses[artist.id] || { status: 'idea', fee: null, notes: '', contactEmail: '' };
                      const tier = getArtistTier(artist.id);
                      const isExpanded = selectedArtistForStatus === artist.id;
                      
                      return (
                        <div
                          key={artist.id}
                          draggable
                          onDragStart={(e) => {
                            e.dataTransfer.setData('artistId', artist.id.toString());
                            e.currentTarget.style.opacity = '0.5';
                          }}
                          onDragEnd={(e) => {
                            e.currentTarget.style.opacity = '1';
                          }}
                          className="rounded-xl border bg-white cursor-grab active:cursor-grabbing transition-shadow hover:shadow-md"
                          style={{ borderColor: isExpanded ? config.color : colors.border }}
                        >
                          {/* Card Header */}
                          <div className="p-3">
                            <div className="flex items-start gap-3">
                              <img 
                                src={artist.image} 
                                alt={artist.name}
                                className="w-10 h-10 rounded-lg object-cover cursor-pointer"
                                onClick={() => onSelectArtist(artist)}
                              />
                              <div className="flex-1 min-w-0">
                                <div 
                                  className="font-semibold text-sm truncate cursor-pointer hover:underline"
                                  style={{ color: colors.primary }}
                                  onClick={() => onSelectArtist(artist)}
                                >
                                  {artist.name}
                                </div>
                                <div className="flex items-center gap-2 mt-1">
                                  <span 
                                    className="text-xs px-1.5 py-0.5 rounded"
                                    style={{ backgroundColor: tierConfig[tier]?.bgColor, color: tierConfig[tier]?.color }}
                                  >
                                    {tierConfig[tier]?.label}
                                  </span>
                                  {status.fee && (
                                    <span className="text-xs font-medium" style={{ color: colors.secondary }}>
                                      ${(status.fee / 1000).toFixed(0)}k
                                    </span>
                                  )}
                                </div>
                              </div>
                              <button
                                onClick={() => setSelectedArtistForStatus(isExpanded ? null : artist.id)}
                                className="p-1 rounded hover:bg-gray-100 transition-colors"
                              >
                                <ChevronDown 
                                  className="w-4 h-4 transition-transform"
                                  style={{ 
                                    color: colors.secondary,
                                    transform: isExpanded ? 'rotate(180deg)' : 'rotate(0deg)'
                                  }} 
                                />
                              </button>
                            </div>
                            
                            {/* Quick Info */}
                            {status.notes && !isExpanded && (
                              <p 
                                className="text-xs mt-2 line-clamp-2"
                                style={{ color: colors.secondary }}
                              >
                                {status.notes}
                              </p>
                            )}
                          </div>
                          
                          {/* Expanded Content */}
                          {isExpanded && (
                            <div className="px-3 pb-3 space-y-3">
                              <div className="h-px" style={{ backgroundColor: colors.border }} />
                              
                              {/* Contact & Fee */}
                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Fee</label>
                                  <input
                                    type="number"
                                    value={status.fee || ''}
                                    onChange={(e) => updateArtistStatus(artist.id, 'fee', e.target.value ? parseInt(e.target.value) : null)}
                                    placeholder="50000"
                                    className="w-full px-2 py-1.5 rounded-lg border text-sm"
                                    style={{ borderColor: colors.border }}
                                    onClick={(e) => e.stopPropagation()}
                                  />
                                </div>
                                <div>
                                  <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Email</label>
                                  <input
                                    type="email"
                                    value={status.contactEmail}
                                    onChange={(e) => updateArtistStatus(artist.id, 'contactEmail', e.target.value)}
                                    placeholder="booking@..."
                                    className="w-full px-2 py-1.5 rounded-lg border text-sm"
                                    style={{ borderColor: colors.border }}
                                    onClick={(e) => e.stopPropagation()}
                                  />
                                </div>
                              </div>
                              
                              {/* Notes */}
                              <div>
                                <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Notes</label>
                                <textarea
                                  value={status.notes}
                                  onChange={(e) => updateArtistStatus(artist.id, 'notes', e.target.value)}
                                  placeholder="Add notes..."
                                  rows={2}
                                  className="w-full px-2 py-1.5 rounded-lg border text-sm resize-none"
                                  style={{ borderColor: colors.border }}
                                  onClick={(e) => e.stopPropagation()}
                                />
                              </div>
                              
                              {/* Action Buttons */}
                              <div className="flex flex-wrap gap-2">
                                {statusId === 'idea' && (
                                  <button
                                    onClick={() => updateArtistStatus(artist.id, 'status', 'outreach')}
                                    className="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                                    style={{ backgroundColor: statusConfig.outreach.color }}
                                  >
                                    <Mail className="w-3 h-3" />
                                    Start Outreach
                                  </button>
                                )}
                                {statusId === 'outreach' && (
                                  <button
                                    onClick={() => updateArtistStatus(artist.id, 'status', 'negotiating')}
                                    className="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                                    style={{ backgroundColor: statusConfig.negotiating.color }}
                                  >
                                    <DollarSign className="w-3 h-3" />
                                    Negotiate
                                  </button>
                                )}
                                {statusId === 'negotiating' && (
                                  <button
                                    onClick={() => updateArtistStatus(artist.id, 'status', 'contract_sent')}
                                    className="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                                    style={{ backgroundColor: statusConfig.contract_sent.color }}
                                  >
                                    <Send className="w-3 h-3" />
                                    Send Contract
                                  </button>
                                )}
                                {statusId === 'contract_sent' && (
                                  <>
                                    <button
                                      onClick={() => updateArtistStatus(artist.id, 'status', 'contract_signed')}
                                      className="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                                      style={{ backgroundColor: statusConfig.contract_signed.color }}
                                    >
                                      <FileSignature className="w-3 h-3" />
                                      Signed
                                    </button>
                                    <button
                                      className="flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium border"
                                      style={{ borderColor: colors.border, color: colors.primary }}
                                    >
                                      <ExternalLink className="w-3 h-3" />
                                      DocuSign
                                    </button>
                                  </>
                                )}
                                {statusId === 'contract_signed' && (
                                  <button
                                    onClick={() => updateArtistStatus(artist.id, 'status', 'confirmed')}
                                    className="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white"
                                    style={{ backgroundColor: statusConfig.confirmed.color }}
                                  >
                                    <CheckCircle className="w-3 h-3" />
                                    Confirm
                                  </button>
                                )}
                                {statusId !== 'declined' && statusId !== 'confirmed' && (
                                  <button
                                    onClick={() => updateArtistStatus(artist.id, 'status', 'declined')}
                                    className="flex items-center justify-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium"
                                    style={{ color: statusConfig.declined.color }}
                                  >
                                    <X className="w-3 h-3" />
                                  </button>
                                )}
                              </div>
                            </div>
                          )}
                        </div>
                      );
                    })}
                    
                    {/* Empty State */}
                    {columnArtists.length === 0 && (
                      <div 
                        className="flex flex-col items-center justify-center py-8 px-4 rounded-xl border-2 border-dashed"
                        style={{ borderColor: colors.border }}
                      >
                        <StatusIcon className="w-8 h-8 mb-2 opacity-30" style={{ color: config.color }} />
                        <p className="text-xs text-center" style={{ color: colors.secondary }}>
                          Drag artists here
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
          
          {/* Drag Instructions */}
          <div className="mt-4 flex items-center justify-center gap-2 text-sm" style={{ color: colors.secondary }}>
            <GripVertical className="w-4 h-4" />
            <span>Drag and drop artists between columns to update their status</span>
          </div>
        </div>
      )}
      
      {activeTab === 'schedule' && (
        <div>
          {/* Day Selector */}
          <div className="flex items-center justify-between mb-6">
            <div className="flex gap-2">
              {days.map(day => (
                <button
                  key={day}
                  onClick={() => setSelectedDay(day)}
                  className="px-4 py-2 rounded-xl font-medium transition-all"
                  style={{
                    backgroundColor: selectedDay === day ? colors.accent : colors.surface,
                    color: selectedDay === day ? 'white' : colors.secondary,
                  }}
                >
                  {day}
                </button>
              ))}
            </div>
            <div className="text-sm" style={{ color: colors.secondary }}>
              {getScheduleForDay(selectedDay).length} acts scheduled
            </div>
          </div>
          
          {/* Timeline View */}
          <div className="border rounded-2xl overflow-hidden" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
            {/* Time Header */}
            <div className="flex border-b" style={{ borderColor: colors.border }}>
              <div className="w-32 p-3 text-xs font-medium shrink-0" style={{ color: colors.secondary, backgroundColor: colors.surface }}>
                Stage
              </div>
              <div className="flex-1 flex">
                {hours.map(hour => (
                  <div 
                    key={hour} 
                    className="flex-1 p-3 text-xs font-medium text-center border-l"
                    style={{ color: colors.secondary, borderColor: colors.border, backgroundColor: colors.surface }}
                  >
                    {hour}:00
                  </div>
                ))}
              </div>
            </div>
            
            {/* Stage Rows */}
            {stages.map(stage => {
              const stageArtists = getScheduleForDay(selectedDay).filter(a => a.schedule.stage === stage);
              return (
                <div key={stage} className="flex border-b last:border-0" style={{ borderColor: colors.border }}>
                  <div 
                    className="w-32 p-3 text-sm font-medium shrink-0 flex items-center"
                    style={{ color: colors.primary, backgroundColor: colors.surface }}
                  >
                    {stage}
                  </div>
                  <div className="flex-1 relative" style={{ minHeight: '80px' }}>
                    {/* Hour grid lines */}
                    <div className="absolute inset-0 flex">
                      {hours.map(hour => (
                        <div 
                          key={hour} 
                          className="flex-1 border-l"
                          style={{ borderColor: colors.border }}
                        />
                      ))}
                    </div>
                    
                    {/* Artist slots */}
                    {stageArtists.map(artist => {
                      const tier = getArtistTier(artist.id);
                      const tierColor = tierConfig[tier]?.color || colors.primary;
                      const left = getTimePosition(artist.schedule.startTime);
                      const width = getSlotWidth(artist.schedule.duration);
                      
                      return (
                        <div
                          key={artist.id}
                          className="absolute top-2 bottom-2 rounded-xl flex items-center gap-2 px-3 cursor-pointer hover:opacity-90 transition-opacity overflow-hidden"
                          style={{
                            left: `${left}%`,
                            width: `${width}%`,
                            backgroundColor: tierColor,
                            minWidth: '120px',
                          }}
                          onClick={() => setEditingSlot(artist.id)}
                        >
                          <img 
                            src={artist.image} 
                            alt={artist.name}
                            className="w-8 h-8 rounded-lg object-cover shrink-0"
                          />
                          <div className="min-w-0">
                            <div className="text-sm font-semibold text-white truncate">{artist.name}</div>
                            <div className="text-xs text-white/70">
                              {artist.schedule.startTime} • {artist.schedule.duration}min
                            </div>
                          </div>
                        </div>
                      );
                    })}
                    
                    {stageArtists.length === 0 && (
                      <div className="absolute inset-0 flex items-center justify-center text-sm" style={{ color: colors.secondary }}>
                        No artists scheduled
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
          
          {/* Artist Schedule List */}
          <div className="mt-8">
            <h3 className="font-bold mb-4" style={{ color: colors.primary }}>All Artists - {selectedDay}</h3>
            <div className="space-y-2">
              {getScheduleForDay(selectedDay).length > 0 ? (
                getScheduleForDay(selectedDay).map(artist => {
                  const tier = getArtistTier(artist.id);
                  return (
                    <div 
                      key={artist.id}
                      className="flex items-center gap-4 p-4 rounded-xl border"
                      style={{ backgroundColor: colors.background, borderColor: colors.border }}
                    >
                      <img src={artist.image} alt={artist.name} className="w-12 h-12 rounded-xl object-cover" />
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold" style={{ color: colors.primary }}>{artist.name}</span>
                          <span 
                            className="text-xs px-2 py-0.5 rounded-full"
                            style={{ backgroundColor: tierConfig[tier]?.bgColor, color: tierConfig[tier]?.color }}
                          >
                            {tierConfig[tier]?.label}
                          </span>
                        </div>
                        <div className="text-sm" style={{ color: colors.secondary }}>
                          {artist.schedule.stage}
                        </div>
                      </div>
                      
                      {/* Time controls */}
                      <div className="flex items-center gap-3">
                        <div>
                          <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Start Time</label>
                          <select
                            value={artist.schedule.startTime}
                            onChange={(e) => updateSchedule(artist.id, 'startTime', e.target.value)}
                            className="px-3 py-1.5 rounded-lg border text-sm"
                            style={{ borderColor: colors.border, color: colors.primary }}
                          >
                            {hours.flatMap(h => ['00', '30'].map(m => (
                              <option key={`${h}:${m}`} value={`${h}:${m}`}>{h}:{m}</option>
                            )))}
                          </select>
                        </div>
                        <div>
                          <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Duration</label>
                          <select
                            value={artist.schedule.duration}
                            onChange={(e) => updateSchedule(artist.id, 'duration', parseInt(e.target.value))}
                            className="px-3 py-1.5 rounded-lg border text-sm"
                            style={{ borderColor: colors.border, color: colors.primary }}
                          >
                            <option value={30}>30 min</option>
                            <option value={45}>45 min</option>
                            <option value={60}>60 min</option>
                            <option value={75}>75 min</option>
                            <option value={90}>90 min</option>
                            <option value={120}>120 min</option>
                          </select>
                        </div>
                        <div>
                          <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Stage</label>
                          <select
                            value={artist.schedule.stage}
                            onChange={(e) => updateSchedule(artist.id, 'stage', e.target.value)}
                            className="px-3 py-1.5 rounded-lg border text-sm"
                            style={{ borderColor: colors.border, color: colors.primary }}
                          >
                            {stages.map(s => (
                              <option key={s} value={s}>{s}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-xs block mb-1" style={{ color: colors.secondary }}>Day</label>
                          <select
                            value={artist.schedule.day}
                            onChange={(e) => updateSchedule(artist.id, 'day', e.target.value)}
                            className="px-3 py-1.5 rounded-lg border text-sm"
                            style={{ borderColor: colors.border, color: colors.primary }}
                          >
                            {days.map(d => (
                              <option key={d} value={d}>{d}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  );
                })
              ) : (
                <div 
                  className="text-center py-12 border-2 border-dashed rounded-xl"
                  style={{ borderColor: colors.border, color: colors.secondary }}
                >
                  <Calendar className="w-12 h-12 mx-auto mb-3 opacity-50" />
                  <p className="font-medium mb-1">No artists scheduled for {selectedDay}</p>
                  <p className="text-sm">Add artists to your lineup and assign them to this day</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      
      {/* Comparison Modal */}
      {showCompareModal && compareArtists.length >= 2 && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowCompareModal(false)} />
          <div 
            className="relative w-full max-w-6xl max-h-[90vh] overflow-auto rounded-2xl shadow-2xl"
            style={{ backgroundColor: colors.background }}
          >
            {/* Modal Header */}
            <div className="sticky top-0 z-10 flex items-center justify-between p-6 border-b" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <div>
                <h2 className="text-2xl font-black" style={{ color: colors.primary }}>Artist Comparison</h2>
                <p className="text-sm" style={{ color: colors.secondary }}>Comparing {compareArtists.length} artists side by side</p>
              </div>
              <button 
                onClick={() => setShowCompareModal(false)}
                className="p-2 rounded-xl hover:bg-gray-100 transition-colors"
              >
                <X className="w-6 h-6" style={{ color: colors.secondary }} />
              </button>
            </div>
            
            {/* Comparison Content */}
            <div className="p-6">
              {/* Artist Headers */}
              <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}>
                <div></div>
                {getCompareArtists().map(artist => {
                  const tier = getArtistTier(artist.id);
                  const status = artistStatuses[artist.id];
                  return (
                    <div key={artist.id} className="text-center">
                      <img 
                        src={artist.image} 
                        alt={artist.name}
                        className="w-24 h-24 rounded-2xl object-cover mx-auto mb-3"
                      />
                      <h3 className="font-bold text-lg" style={{ color: colors.primary }}>{artist.name}</h3>
                      <div className="flex items-center justify-center gap-2 mt-2">
                        <span 
                          className="text-xs px-2 py-1 rounded-full"
                          style={{ backgroundColor: tierConfig[tier]?.bgColor, color: tierConfig[tier]?.color }}
                        >
                          {tierConfig[tier]?.label}
                        </span>
                        {status && (
                          <span 
                            className="text-xs px-2 py-1 rounded-full flex items-center gap-1"
                            style={{ backgroundColor: statusConfig[status.status]?.bgColor, color: statusConfig[status.status]?.color }}
                          >
                            {React.createElement(statusConfig[status.status]?.icon, { className: 'w-3 h-3' })}
                            {statusConfig[status.status]?.label}
                          </span>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
              
              {/* Comparison Rows */}
              <div className="space-y-1">
                {/* Score */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Award className="w-4 h-4" style={{ color: colors.accent }} />
                    Artist Score
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.score === getHighestValue(getCompareArtists(), 'score');
                    return (
                      <div key={artist.id} className="text-center">
                        <span 
                          className={`text-2xl font-black ${isHighest ? 'text-green-600' : ''}`}
                          style={{ color: isHighest ? '#059669' : colors.primary }}
                        >
                          {artist.score}
                        </span>
                        {isHighest && <span className="ml-2 text-xs text-green-600">★ Highest</span>}
                      </div>
                    );
                  })}
                </div>
                
                {/* Spotify Monthly Listeners */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <TrendingUp className="w-4 h-4" style={{ color: '#1DB954' }} />
                    Monthly Listeners
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.spotifyListeners === getHighestValue(getCompareArtists(), 'spotifyListeners');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.spotifyListeners)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Spotify Popularity */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <BarChart3 className="w-4 h-4" style={{ color: '#1DB954' }} />
                    Spotify Popularity
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.spotifyPopularity === getHighestValue(getCompareArtists(), 'spotifyPopularity');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {artist.spotifyPopularity}/100
                        </span>
                        <div className="w-full h-2 rounded-full mt-1" style={{ backgroundColor: colors.border }}>
                          <div 
                            className="h-full rounded-full"
                            style={{ width: `${artist.spotifyPopularity}%`, backgroundColor: isHighest ? '#059669' : colors.accent }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
                
                {/* Spotify Followers */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Users className="w-4 h-4" style={{ color: '#1DB954' }} />
                    Spotify Followers
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.spotifyFollowers === getHighestValue(getCompareArtists(), 'spotifyFollowers');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.spotifyFollowers)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* YouTube Subscribers */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Youtube className="w-4 h-4" style={{ color: '#FF0000' }} />
                    YouTube Subscribers
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.youtubeSubscribers === getHighestValue(getCompareArtists(), 'youtubeSubscribers');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.youtubeSubscribers)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* YouTube Views */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Youtube className="w-4 h-4" style={{ color: '#FF0000' }} />
                    Total YouTube Views
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.youtubeViews === getHighestValue(getCompareArtists(), 'youtubeViews');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.youtubeViews)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Instagram */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Instagram className="w-4 h-4" style={{ color: '#E4405F' }} />
                    Instagram Followers
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.instagramFollowers === getHighestValue(getCompareArtists(), 'instagramFollowers');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.instagramFollowers)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Twitter */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Twitter className="w-4 h-4" style={{ color: '#1DA1F2' }} />
                    Twitter/X Followers
                  </div>
                  {getCompareArtists().map(artist => {
                    const isHighest = artist.twitterFollowers === getHighestValue(getCompareArtists(), 'twitterFollowers');
                    return (
                      <div key={artist.id} className="text-center">
                        <span className={`font-bold ${isHighest ? 'text-green-600' : ''}`} style={{ color: isHighest ? '#059669' : colors.primary }}>
                          {formatNumber(artist.twitterFollowers)}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Divider */}
                <div className="my-4 h-px" style={{ backgroundColor: colors.border }} />
                
                {/* Booking Status Section Header */}
                <div className="mb-2">
                  <h3 className="font-bold text-lg" style={{ color: colors.primary }}>Booking Details</h3>
                </div>
                
                {/* Fee */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <DollarSign className="w-4 h-4" style={{ color: colors.accent }} />
                    Booking Fee
                  </div>
                  {getCompareArtists().map(artist => {
                    const status = artistStatuses[artist.id];
                    const fee = status?.fee;
                    const allFees = getCompareArtists().map(a => artistStatuses[a.id]?.fee || 0);
                    const isLowest = fee && fee === Math.min(...allFees.filter(f => f > 0));
                    return (
                      <div key={artist.id} className="text-center">
                        {fee ? (
                          <>
                            <span className={`font-bold ${isLowest ? 'text-green-600' : ''}`} style={{ color: isLowest ? '#059669' : colors.primary }}>
                              ${fee.toLocaleString()}
                            </span>
                            {isLowest && <span className="ml-2 text-xs text-green-600">★ Best Value</span>}
                          </>
                        ) : (
                          <span style={{ color: colors.secondary }}>Not set</span>
                        )}
                      </div>
                    );
                  })}
                </div>
                
                {/* Contact Email */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Mail className="w-4 h-4" style={{ color: colors.secondary }} />
                    Contact Email
                  </div>
                  {getCompareArtists().map(artist => {
                    const status = artistStatuses[artist.id];
                    return (
                      <div key={artist.id} className="text-center">
                        <span className="text-sm" style={{ color: status?.contactEmail ? colors.primary : colors.secondary }}>
                          {status?.contactEmail || 'Not set'}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Notes */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)`, backgroundColor: colors.surface }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <FileText className="w-4 h-4" style={{ color: colors.secondary }} />
                    Notes
                  </div>
                  {getCompareArtists().map(artist => {
                    const status = artistStatuses[artist.id];
                    return (
                      <div key={artist.id} className="text-center">
                        <span className="text-sm" style={{ color: status?.notes ? colors.primary : colors.secondary }}>
                          {status?.notes || 'No notes'}
                        </span>
                      </div>
                    );
                  })}
                </div>
                
                {/* Genres */}
                <div 
                  className="grid gap-4 p-4 rounded-xl"
                  style={{ gridTemplateColumns: `200px repeat(${compareArtists.length}, 1fr)` }}
                >
                  <div className="font-semibold flex items-center gap-2" style={{ color: colors.primary }}>
                    <Music className="w-4 h-4" style={{ color: colors.secondary }} />
                    Genres
                  </div>
                  {getCompareArtists().map(artist => (
                    <div key={artist.id} className="text-center">
                      <div className="flex flex-wrap justify-center gap-1">
                        {artist.genre.map(g => (
                          <span 
                            key={g}
                            className="text-xs px-2 py-1 rounded-full"
                            style={{ backgroundColor: colors.surface, color: colors.secondary }}
                          >
                            {g}
                          </span>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            
            {/* Modal Footer */}
            <div className="sticky bottom-0 p-6 border-t flex items-center justify-between" style={{ backgroundColor: colors.background, borderColor: colors.border }}>
              <p className="text-sm" style={{ color: colors.secondary }}>
                <span className="text-green-600">★</span> indicates the best value in each category
              </p>
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setShowCompareModal(false)}
                  className="px-6 py-2 rounded-xl font-medium transition-colors"
                  style={{ backgroundColor: colors.surface, color: colors.primary }}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Stack Management Modal */}
      {showStackModal && artistStacks[showStackModal] && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/50" onClick={() => setShowStackModal(null)} />
          <div 
            className="relative w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden"
            style={{ backgroundColor: colors.background }}
          >
            {/* Modal Header */}
            <div className="p-6 border-b" style={{ borderColor: colors.border }}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-xl" style={{ backgroundColor: '#f3e8ff' }}>
                    <Layers className="w-5 h-5" style={{ color: '#8b5cf6' }} />
                  </div>
                  <div>
                    <h2 className="text-xl font-bold" style={{ color: colors.primary }}>Manage Alternatives</h2>
                    <p className="text-sm" style={{ color: colors.secondary }}>
                      Choose between these artists for this slot
                    </p>
                  </div>
                </div>
                <button 
                  onClick={() => setShowStackModal(null)}
                  className="p-2 rounded-xl hover:bg-gray-100 transition-colors"
                >
                  <X className="w-5 h-5" style={{ color: colors.secondary }} />
                </button>
              </div>
            </div>
            
            {/* Stack Content */}
            <div className="p-6">
              {(() => {
                const stack = artistStacks[showStackModal];
                const primary = mockArtists.find(a => a.id === stack.primary);
                const alternatives = stack.alternatives.map(id => mockArtists.find(a => a.id === id)).filter(Boolean);
                
                return (
                  <div className="space-y-4">
                    {/* Primary Artist */}
                    <div>
                      <div className="text-xs font-medium mb-2 flex items-center gap-2" style={{ color: '#8b5cf6' }}>
                        <CheckCircle className="w-3 h-3" />
                        PRIMARY CHOICE
                      </div>
                      <div 
                        className="flex items-center gap-4 p-4 rounded-xl border-2"
                        style={{ backgroundColor: '#f3e8ff', borderColor: '#8b5cf6' }}
                      >
                        <img 
                          src={primary?.image} 
                          alt={primary?.name}
                          className="w-14 h-14 rounded-xl object-cover"
                        />
                        <div className="flex-1">
                          <div className="font-bold" style={{ color: colors.primary }}>{primary?.name}</div>
                          <div className="flex items-center gap-2 mt-1">
                            <span className="text-sm" style={{ color: colors.secondary }}>Score: {primary?.score}</span>
                            {artistStatuses[primary?.id] && (
                              <span 
                                className="text-xs px-2 py-0.5 rounded-full flex items-center gap-1"
                                style={{ 
                                  backgroundColor: statusConfig[artistStatuses[primary?.id].status]?.bgColor,
                                  color: statusConfig[artistStatuses[primary?.id].status]?.color
                                }}
                              >
                                {React.createElement(statusConfig[artistStatuses[primary?.id].status]?.icon, { className: 'w-3 h-3' })}
                                {statusConfig[artistStatuses[primary?.id].status]?.label}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    {/* Alternatives */}
                    {alternatives.length > 0 && (
                      <div>
                        <div className="text-xs font-medium mb-2" style={{ color: colors.secondary }}>
                          ALTERNATIVES ({alternatives.length})
                        </div>
                        <div className="space-y-2">
                          {alternatives.map(alt => (
                            <div 
                              key={alt.id}
                              className="flex items-center gap-4 p-4 rounded-xl border"
                              style={{ backgroundColor: colors.background, borderColor: colors.border }}
                            >
                              <img 
                                src={alt.image} 
                                alt={alt.name}
                                className="w-12 h-12 rounded-xl object-cover"
                              />
                              <div className="flex-1">
                                <div className="font-semibold" style={{ color: colors.primary }}>{alt.name}</div>
                                <div className="flex items-center gap-2 mt-1">
                                  <span className="text-sm" style={{ color: colors.secondary }}>Score: {alt.score}</span>
                                  {artistStatuses[alt.id] && (
                                    <span 
                                      className="text-xs px-2 py-0.5 rounded-full flex items-center gap-1"
                                      style={{ 
                                        backgroundColor: statusConfig[artistStatuses[alt.id].status]?.bgColor,
                                        color: statusConfig[artistStatuses[alt.id].status]?.color
                                      }}
                                    >
                                      {React.createElement(statusConfig[artistStatuses[alt.id].status]?.icon, { className: 'w-3 h-3' })}
                                      {statusConfig[artistStatuses[alt.id].status]?.label}
                                    </span>
                                  )}
                                </div>
                              </div>
                              <div className="flex items-center gap-2">
                                <button
                                  onClick={() => {
                                    promoteAlternative(showStackModal, alt.id);
                                  }}
                                  className="px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1"
                                  style={{ backgroundColor: '#d1fae5', color: '#059669' }}
                                >
                                  <ArrowUp className="w-3 h-3" />
                                  Make Primary
                                </button>
                                <button
                                  onClick={() => {
                                    removeFromStack(showStackModal, alt.id);
                                    removeArtist(alt.id, stack.tier);
                                  }}
                                  className="p-1.5 rounded-lg hover:bg-red-50 hover:text-red-500 transition-colors"
                                  style={{ color: colors.secondary }}
                                >
                                  <X className="w-4 h-4" />
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                    
                    {/* Add More Button */}
                    <button
                      onClick={() => {
                        setShowStackModal(null);
                        setStackMode(true);
                        setStackingArtist({ stackId: showStackModal, artistId: stack.primary, tier: stack.tier });
                      }}
                      className="w-full p-3 rounded-xl border-2 border-dashed flex items-center justify-center gap-2 transition-colors hover:bg-purple-50"
                      style={{ borderColor: '#8b5cf6', color: '#8b5cf6' }}
                    >
                      <Plus className="w-4 h-4" />
                      Add Another Alternative
                    </button>
                  </div>
                );
              })()}
            </div>
            
            {/* Modal Footer */}
            <div className="p-6 border-t flex items-center justify-between" style={{ borderColor: colors.border }}>
              <button
                onClick={() => {
                  dissolveStack(showStackModal);
                  setShowStackModal(null);
                }}
                className="px-4 py-2 rounded-xl text-sm font-medium flex items-center gap-2 hover:bg-red-50 transition-colors"
                style={{ color: '#ef4444' }}
              >
                <Unlink className="w-4 h-4" />
                Dissolve Stack
              </button>
              <button
                onClick={() => setShowStackModal(null)}
                className="px-6 py-2 rounded-xl font-medium"
                style={{ backgroundColor: '#8b5cf6', color: 'white' }}
              >
                Done
              </button>
            </div>
          </div>
        </div>
      )}
        </div>
      </div>
    </div>
  );
};

// Main App
export default function ArtistTreeMockups() {
  const [activePage, setActivePage] = useState('dashboard');
  const [selectedArtist, setSelectedArtist] = useState(null);
  const [selectedLineup, setSelectedLineup] = useState(null);
  const [lineups, setLineups] = useState(mockLineups);
  
  const handleNavigate = (page) => {
    setActivePage(page);
    setSelectedArtist(null);
    setSelectedLineup(null);
  };
  
  const handleSelectArtist = (artist) => {
    setSelectedArtist(artist);
  };
  
  const handleBackFromArtist = () => {
    setSelectedArtist(null);
  };
  
  const handleSelectLineup = (lineup) => {
    setSelectedLineup(lineup);
  };
  
  const handleBackFromLineup = () => {
    setSelectedLineup(null);
  };
  
  const handleCreateLineup = (newLineup) => {
    setLineups(prev => [newLineup, ...prev]);
    setSelectedLineup(newLineup);
  };
  
  // Determine current view for sidebar highlighting
  const getCurrentPage = () => {
    if (selectedArtist) return 'search';
    if (selectedLineup) return 'lineups';
    return activePage;
  };
  
  return (
    <div className="min-h-screen" style={{ backgroundColor: colors.background, fontFamily: "'DM Sans', sans-serif" }}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap');
        * { scrollbar-width: thin; scrollbar-color: #e5e5e5 transparent; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e5e5e5; border-radius: 3px; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 20px; height: 20px; border-radius: 50%; background: ${colors.accent}; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        input[type="range"]::-moz-range-thumb { width: 20px; height: 20px; border-radius: 50%; background: ${colors.accent}; cursor: pointer; border: none; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
      `}</style>
      
      <Sidebar activePage={getCurrentPage()} setActivePage={handleNavigate} />
      
      <main className="pl-72 p-8">
        <div className="max-w-7xl mx-auto">
          {selectedArtist ? (
            <ArtistDetail 
              artist={selectedArtist} 
              onBack={handleBackFromArtist}
              onNavigateToArtist={handleSelectArtist}
            />
          ) : selectedLineup ? (
            <LineupDetail 
              lineup={selectedLineup}
              onBack={handleBackFromLineup}
              onSelectArtist={handleSelectArtist}
            />
          ) : (
            <>
              {activePage === 'dashboard' && <Dashboard onNavigate={handleNavigate} onSelectArtist={handleSelectArtist} />}
              {activePage === 'search' && <ArtistSearch onNavigate={handleNavigate} onSelectArtist={handleSelectArtist} />}
              {activePage === 'lineups' && <LineupsPage onSelectLineup={handleSelectLineup} onCreateLineup={handleCreateLineup} />}
              {activePage === 'settings' && <OrgSettings />}
            </>
          )}
        </div>
      </main>
    </div>
  );
}

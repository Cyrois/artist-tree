# Artist-Tree Product Requirements Document

**Version:** 2.0
**Last Updated:** 2025-12-23
**Tech Stack:** Laravel 12 + Inertia.js v2 + Vue 3 + Laravel Cloud
**Architecture:** Hybrid (Inertia.js pages + RESTful API)

---

## Executive Summary

Artist-Tree is a festival lineup builder and artist discovery platform that helps music festival organizers, promoters, and enthusiasts create balanced lineups by providing data-driven insights on artist popularity, engagement metrics, and tier classifications.

---

## Design System

### Color Palette
- **Primary:** #1a1a1a (Charcoal - text, headings)
- **Secondary:** #6b7280 (Gray - secondary text, labels)
- **Accent:** #e85d4c (Coral - CTAs, highlights, active states)
- **Background:** #ffffff (White)
- **Surface:** #f8f8f8 (Light gray - cards, sections)
- **Border:** #e5e5e5 (Subtle borders)

### Typography
- **Font Family:** DM Sans
- **Headings:** Font-black (900 weight)
- **Body:** Regular (400 weight)
- **Labels:** Medium (500 weight)

### Component Styles
- **Border Radius:** 2xl (16px) for cards, xl (12px) for buttons
- **Shadows:** Subtle, used sparingly for elevation
- **Icons:** Lucide React icon library

---

## UI Pages & Features

### 1. Dashboard
**Purpose:** Landing page with quick access to lineups and artist search

**Components:**
- **Hero Section**
  - Title: "Build Your Dream Lineup"
  - Full-width search bar with typeahead
  - Search results dropdown (z-index prioritized, appears above all content)
  
- **Your Lineups Section**
  - Card for each lineup showing:
    - Lineup name and description
    - Artist count
    - Average score (calculated from lineup artists)
    - Confirmed bookings count (green)
    - Pending bookings count (amber)
    - Total budget
  - Click to navigate to lineup detail

**Removed:**
- ~~Global stats cards (Active Lineups, Artists Saved, Avg Score)~~
- ~~Trending Artists section~~ (moved to Search page)

---

### 2. Search Artists Page
**Purpose:** Discover and search for artists to add to lineups

**Layout:**
- Responsive grid: 2 cols (mobile) → 3 cols (md) → 4 cols (lg) → 5 cols (xl)

**Components:**
- **Search Bar**
  - Full-width input with search icon
  - Filters button (genre, score range)
  - Search button
  
- **Filter Panel** (collapsible)
  - Genre pills (multi-select)
  - Score range inputs (min/max)
  - Clear filters button
  
- **Compact Artist Cards**
  - Artist image (small, rounded)
  - Artist name with score badge
  - Genre tags (max 2)
  - Monthly listeners count
  - Hover: shadow + arrow indicator
  
- **Search Results Section**
  - Shows after searching
  - Sort dropdown (Score, Name, Listeners)
  - Results count
  
- **Similar Artists Section**
  - Appears below search results
  - Shows artists with overlapping genres not in results
  - Max 5 artists
  
- **Trending Artists Section**
  - Always visible at bottom
  - Top 10 artists by Spotify listeners
  - Same compact card format

---

### 3. Artist Detail Page
**Purpose:** View detailed artist information and add to lineups

**Header Section:**
- Artist image (160x160, rounded)
- Artist name (large, bold)
- Genre tags
- Country and label info
- Score badge (large)
- **Action Buttons:**
  - "Add to Lineup" (accent color, primary CTA)
  - "Compare" (surface color, opens comparison modal)

**Tabs:**
1. **Overview Tab**
   - Bio paragraph
   - Quick Stats grid (4 columns):
     - Monthly Listeners
     - Spotify Popularity
     - YouTube Subscribers
     - Instagram Followers
   - Top Tracks list (top 5)
   - Recent Releases list (top 5)
   - Similar Artists (compact cards, 3 columns)
   - External Links section:
     - Spotify (green branded button)
     - YouTube (red branded button)
     - Instagram (pink branded button)
     - X/Twitter (black branded button)

2. **Data & Metrics Tab**
   - Detailed metrics grid (8 cards):
     - Monthly Listeners (with % change)
     - Spotify Popularity
     - Spotify Followers
     - YouTube Subscribers
     - Instagram Followers
     - Twitter Followers
     - Total YouTube Views
     - Active Since (year)
   - Monthly Listeners Trend chart (bar chart, 5 months)
   - Score Breakdown (progress bars showing weight contribution)
   - Data freshness footer with "Refresh Data" button

**Removed from Artist Detail:**
- ~~Tier Suggestion banner~~ (moved to Add to Lineup modal)
- ~~External links in header~~ (moved to Overview tab bottom)

---

### 4. Add to Lineup Modal (Wizard Flow)
**Purpose:** Add artist to a lineup with tier selection

**Step 1: Select Lineup**
- Modal title: "Select Lineup"
- Artist info card (image, name, score)
- List of available lineups
- Click lineup to advance to step 2

**Step 2: Select Tier**
- Back button (chevron) to return to step 1
- Modal title: "Select Tier"
- Artist info card shows "Adding to [lineup name]"
- Tier options as radio buttons:
  - Headliner
  - Sub-Headliner
  - Supporting
  - Emerging
  - Local/Regional
- **Suggested tier is pre-selected** on entry
- Suggested tier shows "✨ Suggested" badge
- "Add to Lineup" button

**Success State:**
- Checkmark animation
- "Added!" message
- Shows lineup name and tier
- Auto-closes after 1.5 seconds

---

### 5. Artist Comparison Modal
**Purpose:** Compare two artists side-by-side

**Entry:** Click "Compare" button on Artist Detail page

**Step 1: Select Artist to Compare**
- Search bar to find artists
- Search results as clickable cards
- "Suggested comparisons" section showing similar artists
- Click artist to start comparison

**Step 2: Comparison View**
- 3-column layout:
  - Column 1: Metric labels with icons
  - Column 2: Current artist stats (under their photo/name)
  - Column 3: Comparison artist stats (under their photo/name)
- Artist headers with image, name, genre
- Comparison rows (alternating background):
  - Artist Score
  - Monthly Listeners
  - Spotify Popularity
  - Spotify Followers
  - YouTube Subscribers
  - Instagram Followers
  - Twitter Followers
- **Winner highlighting:** Green color + ★ for higher value
- "Compare with different artist" button to reset

---

### 6. Lineup Detail Page
**Purpose:** Manage lineup artists, booking pipeline, and schedule

**Header:**
- Back button
- Editable lineup name (click to edit)
- Artist count and last updated
- Export button

**Tab Bar:**
- Full-width, spans edge to edge
- Underline style for active tab (coral accent)
- Subtle shadow/border separating from content
- Tabs: Lineup | Booking | Schedule

**Content Area:**
- Different background color (surface gray)
- Full-width background extending edge to edge

---

### 6a. Lineup Tab
**Purpose:** View and organize artists by tier

**Toolbar:**
- Combined search + actions bar
- Left: Search input ("Search and add artists...")
- Divider
- Right: Stack button, Compare button

**Mode Banners (contextual):**
- **Stack Mode Banner** (purple)
  - Shows when stack mode active
  - Instructions for stacking
  - "Done Adding" button when stacking artist selected
  
- **Compare Mode Banner** (coral)
  - Shows when compare mode active
  - Selected artist count and avatars
  - Clear and Compare buttons

**Tier Sections:**
- Collapsible tier headers with count
- Tiers: Headliner, Sub-Headliner, Supporting, Emerging, Local/Regional

**Artist Cards:**
- Image, name (with status icon to the right of name)
- Score badge
- Tier suggestion sparkle (if different from current tier)
- Hover actions: Stack, Compare, More menu, Remove

**Stacked Artists:**
- Primary artist shows Layers badge
- Alternatives nested below with purple left border
- Smaller cards with promote/remove actions

---

### 6b. Booking Tab
**Purpose:** Track booking pipeline status

**Kanban Board Layout:**
- 7 columns representing booking stages:
  1. **Idea** (purple) - Lightbulb icon
  2. **Outreach** (blue) - Mail icon
  3. **Negotiating** (amber) - DollarSign icon
  4. **Contract Sent** (orange) - FileSignature icon
  5. **Contract Signed** (cyan) - CheckCircle icon
  6. **Confirmed** (green) - CheckCircle icon
  7. **Declined** (red) - AlertCircle icon

**Artist Cards in Pipeline:**
- Compact card with image, name, tier badge
- Fee amount (if set)
- Drag-and-drop between columns

---

### 6c. Schedule Tab
**Purpose:** Plan performance times on timeline

**Timeline Grid:**
- Rows: Stages (Main Stage, Second Stage, etc.)
- Columns: Time slots
- Artist blocks positioned on grid
- Visual representation of set times

---

### 7. Organization Settings Page
**Purpose:** Manage scoring weights and team members

**Scoring Weights Section:**
- Preset buttons (Balanced, Streaming-Focused, Social Media-Focused)
- Custom weight sliders:
  - Spotify Monthly Listeners
  - Spotify Popularity
  - YouTube Subscribers
- Total must equal 100%
- Save Changes button

**Team Members Section:**
- Member list with roles (Owner, Admin, Member)
- Invite member form
- Role management dropdown
- Remove member action

---

## Booking Status Configuration

| Status | Color | Icon | Description |
|--------|-------|------|-------------|
| Idea | Purple (#8b5cf6) | Lightbulb | Initial consideration |
| Outreach | Blue (#3b82f6) | Mail | Contact initiated |
| Negotiating | Amber (#f59e0b) | DollarSign | Discussing terms |
| Contract Sent | Orange (#f97316) | FileSignature | Contract delivered |
| Contract Signed | Cyan (#06b6d4) | CheckCircle | Awaiting confirmation |
| Confirmed | Green (#059669) | CheckCircle | Booking confirmed |
| Declined | Red (#dc2626) | AlertCircle | Artist declined |

---

## Artist Stacking Feature

**Purpose:** Group alternative artists for the same slot (e.g., "The Weeknd OR Bad Bunny for Sunday headliner")

**Behavior:**
- Only available on Lineup tab (not Booking/Schedule)
- Purple color theme for stack-related UI
- Stack mode toggle in toolbar
- Click Layers icon on artist card to make it primary
- Click other artists to add as alternatives
- Alternatives display nested under primary with indentation

**Stack Management Modal:**
- Access via "..." menu on stacked primary
- Shows primary (highlighted) and all alternatives
- Actions: Make Primary, Remove from stack
- "Add Another Alternative" button
- "Dissolve Stack" removes all alternatives

**Data Structure:**
```javascript
artistStacks: {
  [stackId]: {
    primary: artistId,
    alternatives: [artistId, artistId],
    tier: 'headliner'
  }
}
```

---

## Technical Architecture

*(Unchanged from v1.1 - see original PRD for full technical details)*

### Backend: Laravel 12
- **Framework:** Laravel 12.x
- **Purpose:** Monolithic application serving both Inertia pages and RESTful API endpoints
- **Key Features:**
  - Eloquent ORM for data modeling
  - Inertia.js for server-side rendering of Vue pages
  - API resource transformers for JSON responses
  - Job queues for async API data fetching (Spotify/YouTube)
  - Service layer for business logic and external API integration
  - Cache layer for API response optimization (Redis/file cache)
  - Laravel Sanctum for cookie-based authentication

### Frontend: Inertia.js v2 + Vue 3
- **Framework:** Inertia.js v2 with Vue 3 Composition API
- **Purpose:** Interactive user interface with server-side routing
- **Key Features:**
  - Component-based architecture with `<script setup>`
  - Reactive state management with `ref()` and `reactive()`
  - Composables for reusable logic
  - Inertia routing (server-side) for page navigation
  - Axios for API calls within components (search, tier updates)
  - Tailwind CSS v4 for styling
  - Laravel Wayfinder for TypeScript route helpers

**Routing Strategy:**
- **Web Routes** (`routes/web.php`): Inertia pages (Dashboard, Lineups, etc.)
- **API Routes** (`routes/api.php`): RESTful JSON endpoints for interactive features

### Database: Laravel Cloud (PostgreSQL)
- **Platform:** Laravel Cloud (Serverless PostgreSQL)
- **Purpose:** Primary data storage with Laravel integration
- **Key Features:**
  - Serverless PostgreSQL database with automatic backups
  - Optimized for Laravel applications
  - Built-in database monitoring and performance insights
  - Seamless integration with Laravel migrations and Eloquent ORM
  - Automatic scaling and optimization

### Deployment
- **Platform:** Laravel Cloud (single deployment)
- **Components:** Backend + Frontend + Database (monolithic)
- **No CORS needed:** Same origin (Inertia + API on same server)

---

## Database Schema Updates

### lineup_artists (updated)
```
- id (primary key)
- lineup_id (foreign key)
- artist_id (foreign key)
- tier (enum: headliner, sub_headliner, supporting, emerging, local)
- suggested_tier (enum)
- tier_override (boolean)
- booking_status (enum: idea, outreach, negotiating, contract_sent, contract_signed, confirmed, declined)
- booking_fee (decimal, nullable)
- stack_id (uuid, nullable) - Groups stacked artists
- is_stack_primary (boolean, default false)
- added_at (timestamp)
```

---

## User Flows (Updated)

### Add Artist to Lineup Flow
1. User views artist detail page
2. Clicks "Add to Lineup" button
3. **Step 1:** Modal shows lineup selection
4. User selects lineup → advances to step 2
5. **Step 2:** Modal shows tier selection with suggestion pre-selected
6. User confirms or changes tier
7. Clicks "Add to Lineup"
8. Success animation shows
9. Modal auto-closes

### Compare Artists Flow
1. User views artist detail page
2. Clicks "Compare" button
3. Modal shows search + suggested similar artists
4. User selects comparison artist
5. Side-by-side comparison displays
6. Winner highlighted for each metric
7. User can change comparison or close

### Stack Artists Flow
1. User on Lineup tab clicks "Stack" button
2. Stack mode activates (purple banner)
3. User clicks Layers icon on primary artist
4. Banner updates with primary artist name
5. User clicks other artists to add as alternatives
6. Alternatives nest under primary
7. User clicks "Done Adding" or "Exit Stack"

---

## Success Metrics

*(Updated)*

1. **Technical Metrics:**
   - API error rate < 1%
   - Average API response time < 1s
   - Modal interactions < 300ms
   - Zero critical security vulnerabilities

2. **User Metrics:**
   - Users can create a lineup in < 5 minutes
   - 80% task completion rate
   - < 5% bounce rate on artist search
   - Artist comparison used in 30%+ of sessions
   - Stacking feature adoption > 20% for multi-artist lineups

3. **Data Quality:**
   - 95% of artists have complete metrics
   - Data freshness within 24 hours
   - < 5% failed API fetches

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-12-20 | Initial PRD |
| 1.1 | 2025-12-21 | Added technical architecture details |
| 2.0 | 2025-12-23 | Major UI/UX updates: Dashboard lineup stats, Search page redesign, Artist detail tabs, Add to Lineup wizard, Artist comparison modal, Stacking feature, Booking pipeline, Full-width tabs layout |

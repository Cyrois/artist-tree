# Artist-Tree Product Requirements Document

**Version:** 1.1
**Last Updated:** 2025-12-21
**Tech Stack:** Laravel 12 + Inertia.js v2 + Vue 3 + Laravel Cloud
**Architecture:** Hybrid (Inertia.js pages + RESTful API)

---

## Executive Summary

Artist-Tree is a festival lineup builder and artist discovery platform that helps music festival organizers, promoters, and enthusiasts create balanced lineups by providing data-driven insights on artist popularity, engagement metrics, and tier classifications.

---

## Technical Architecture

**Architecture Pattern:** Hybrid Monolith (Inertia.js + RESTful API)

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

### Database: Laravel Cloud (MySQL)
- **Platform:** Laravel Cloud (Managed MySQL)
- **Purpose:** Primary data storage with Laravel integration
- **Key Features:**
  - Managed MySQL database with automatic backups
  - Optimized for Laravel applications
  - Built-in database monitoring and performance insights
  - Seamless integration with Laravel migrations and Eloquent ORM
  - Automatic scaling and optimization

### Deployment
- **Platform:** Laravel Cloud (single deployment)
- **Components:** Backend + Frontend + Database (monolithic)
- **No CORS needed:** Same origin (Inertia + API on same server)

---

## Core Features & Business Requirements

### 0. Organization Management & Multi-Tenancy
**Business Need:** Enable teams/organizations to collaborate on lineups with shared scoring preferences

**Technical Implementation:**
- Auto-create personal organization on user signup
- Many-to-many relationship (users can join multiple organizations in future)
- Three role levels: Owner, Admin, Member
- Scoring weights stored per-organization
- Lineups belong to organizations (not individual users)

**Key Features:**
- **Auto-Organization:** New users automatically get "{Name}'s Organization" created
- **Role-Based Access Control:**
  - Owner: Full control (weights, members, lineups, billing)
  - Admin: Manage weights and members
  - Member: Create lineups using org weights (read-only on weights)
- **Weight Presets:** Balanced, Streaming-Focused, Social Media-Focused
- **Member Invitations:** Owners/Admins can invite team members via email
- **Organization Settings Page:** Inertia page for managing weights and members

**Success Criteria:**
- User signup auto-creates organization in < 1 second
- Weight changes apply to all future lineup calculations
- Members can only see lineups from their organization(s)
- Owners/Admins can update scoring weights via settings UI
- Role-based permissions enforced via Laravel Policies

### 1. Artist Data Aggregation
**Business Need:** Provide comprehensive, up-to-date artist metrics from multiple sources

**Technical Implementation:**
- Laravel services to fetch data from Spotify API and YouTube API
- Laravel database tables: `artists`, `artist_metrics`
- **24-hour caching layer** to minimize API calls and stay within rate limits
- Background jobs for periodic data refresh (optional)
- **Empty database at launch** - artists added on-demand as users search

**Artist Discovery Flow:**
1. User searches for artist name
2. Check if artist exists in local database
3. If not found → Call Spotify API to search and fetch data
4. Create artist record + metrics record
5. Cache for 24 hours
6. Return artist data to user

**Success Criteria:**
- Artist data cached for 24 hours (minimizes API calls)
- < 2 second response time for cached data
- < 5 second response time for new artist lookup (API call)
- 99% API call success rate with retry logic
- Artists persist in database after first search (build up artist library over time)

### 2. Artist Scoring Algorithm
**Business Need:** Objectively rank artists based on quantifiable metrics using customizable weighting

**Technical Implementation:**
- Laravel service class for score calculation (`ArtistScoringService`)
- Algorithm considers: Spotify monthly listeners, Spotify popularity, YouTube subscribers, engagement rates
- **Weights pulled from organization's configuration**
- Scores calculated dynamically based on org-specific weights
- Stored in `artist_metrics` table with timestamp (cached for performance)
- Unit tested with various artist profiles and different weight configurations

**Scoring Weights (Per Organization):**
- `spotify_monthly_listeners_weight` (0.00 - 1.00)
- `spotify_popularity_weight` (0.00 - 1.00)
- `youtube_subscribers_weight` (0.00 - 1.00)
- `engagement_rate_weight` (0.00 - 1.00)
- **Validation:** Weights must sum to 1.00

**Preset Configurations (MVP):**
- **Balanced:** 40% Spotify listeners, 30% Spotify popularity, 30% YouTube
- **Streaming-Focused:** 55% Spotify listeners, 30% Spotify popularity, 15% YouTube
- **Social Media-Focused:** 20% Spotify listeners, 15% Spotify popularity, 65% YouTube

**Note:** engagement_rate removed from MVP. When added later, preset weights will need rebalancing.

**Metric Normalization:**
- Use **logarithmic normalization** to handle extreme range (100 followers → 100M followers)
- Formula: `normalized_score = (log10(value + 1) / log10(max_expected)) * 100`
- Max expected values (configurable in `config/artist-tree.php`):
  - `spotify_monthly_listeners`: 100,000,000 (100M)
  - `spotify_popularity`: 100 (already normalized 0-100, no transform needed)
  - `youtube_subscribers`: 50,000,000 (50M)
- Rationale: Industry standard approach, fair to unknowns and superstars, diminishing returns at scale

**Handling Missing Data:**
- If artist is missing data for an **enabled** metric → Treat as **zero (0)**
- Missing data heavily penalizes that metric's contribution to overall score
- Organizations can **disable** metrics to avoid penalizing artists without that platform presence
- Example: Org enables YouTube (30% weight), artist has no channel → Gets 0 for 30% of score
- Example: Org disables YouTube entirely → Artist not penalized for missing YouTube

**Success Criteria:**
- Scores normalized to 0-100 scale
- Reproducible results for same input data + same weights
- Different organizations can get different scores for same artist based on their weights
- Owners/Admins can switch between presets or customize weights
- Weight validation prevents invalid configurations (sum != 1.00)
- Artists with all NULL/missing data receive score of 0

### 3. Lineup Builder
**Business Need:** Allow users to create festival lineups with automatic tier assignment

**Technical Implementation:**
- Laravel database tables: `lineups`, `lineup_artists`
- Laravel API endpoints for CRUD operations
- Vue.js drag-and-drop interface
- Real-time tier recalculation on artist addition/removal

**Success Criteria:**
- Support 50+ artists per lineup
- Tier assignments update in < 1 second
- Lineups persist across sessions

### 4. Tier Classification with Manual Override
**Business Need:** Suggest tier placement based on artist scores, with ability for users to manually override

**Technical Implementation:**
- Algorithm calculates **suggested tier** based on relative scores within lineup
- Users can **manually override** tier via drag-and-drop interface
- Store both `tier` (current) and `suggested_tier` in `lineup_artists` pivot table
- `tier_override` boolean tracks manual vs automatic placement
- Visual indicators show which artists were manually placed

**Suggested Tier Algorithm:**
- Top 10% of artists → Headliner (minimum 1)
- Next 20% → Sub-Headliner
- Next 40% → Mid-Tier
- Bottom 30% → Undercard

**Override Behavior:**
- When artist added: `tier = suggested_tier`, `tier_override = false`
- User drags to new tier: Update `tier`, set `tier_override = true`
- When lineup changes (add/remove artist):
  - Recalculate ALL `suggested_tier` values
  - Auto-placed artists (`tier_override = false`): Update `tier` to new `suggested_tier`
  - Manual-placed artists (`tier_override = true`): Keep current `tier`, update only `suggested_tier`
- "Reset to Suggested" button: Copy all `suggested_tier` to `tier`, set `tier_override = false`

**Success Criteria:**
- Algorithm suggests appropriate tiers based on scores
- Users can override any tier assignment
- Manual overrides persist when lineup changes
- Visual distinction between auto and manual placements
- Reset functionality restores algorithmic suggestions

### 5. Search & Discovery
**Business Need:** Enable users to find and add artists efficiently

**Technical Implementation:**
- Full-text search using Laravel Scout (with database driver or Meilisearch)
- Vue.js search component with debouncing
- Autocomplete suggestions
- Filter by genre, popularity range

**Success Criteria:**
- Search results appear in < 500ms
- Support partial name matching
- Return top 20 relevant results

---

## Data Models (Database Schema)

**Primary Key Strategy:** Auto-increment IDs (standard Laravel)

### Organizations Table
```sql
- id (bigint unsigned, primary key, auto-increment)
- name (varchar 255)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)

Note: Scoring weights now stored in metric_weights table for extensibility
```

### Metric Weights Table
```sql
- id (bigint unsigned, primary key, auto-increment)
- organization_id (bigint unsigned, foreign key -> organizations.id)
- metric_name (varchar 100) -- 'spotify_monthly_listeners', 'youtube_subscribers', etc.
- weight (decimal 3,2) -- 0.00 to 1.00
- enabled (boolean, default true)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (organization_id, metric_name)
- KEY (enabled)
- FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE

Validation:
- SUM(weight) WHERE enabled=true must equal 1.00 for each organization

Available Metrics (MVP Launch):
- spotify_monthly_listeners - Monthly listener count from Spotify
- spotify_popularity - Spotify's proprietary popularity score (0-100)
- youtube_subscribers - YouTube channel subscriber count

Future Metrics (Add via INSERT when ready):
- engagement_rate - Calculate from likes/comments/followers ratio
- instagram_followers
- tiktok_followers
- apple_music_listeners
- soundcloud_plays
- bandcamp_sales
```

### Organization User Table (Pivot)
```sql
- id (bigint unsigned, primary key, auto-increment)
- organization_id (bigint unsigned, foreign key -> organizations.id)
- user_id (bigint unsigned, foreign key -> users.id)
- role (enum: 'owner', 'admin', 'member', default 'member')
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (organization_id, user_id) -- user can only have one role per org
- KEY (user_id)
- KEY (role)
- FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
```

### Artists Table
```sql
- id (bigint unsigned, primary key, auto-increment)
- name (varchar 255)
- spotify_id (varchar 255, unique, nullable)
- youtube_channel_id (varchar 255, nullable)
- genre (json) -- array of genre strings
- image_url (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (spotify_id)
- FULLTEXT KEY (name) -- for artist search
```

### Artist Metrics Table
```sql
- id (bigint unsigned, primary key, auto-increment)
- artist_id (bigint unsigned, foreign key -> artists.id)
- score (decimal 5,2) -- 0.00 to 100.00
- spotify_monthly_listeners (bigint unsigned, nullable)
- youtube_subscribers (bigint unsigned, nullable)
- engagement_rate (decimal 5,2, nullable)
- last_refreshed (timestamp, nullable)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)
- KEY (artist_id)
- KEY (score DESC) -- for sorting by popularity
- FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
```

### Lineups Table
```sql
- id (bigint unsigned, primary key, auto-increment)
- organization_id (bigint unsigned, foreign key -> organizations.id)
- created_by_user_id (bigint unsigned, foreign key -> users.id, nullable)
- name (varchar 255)
- description (text, nullable)
- created_at (timestamp)
- updated_at (timestamp)

Indexes:
- PRIMARY KEY (id)
- KEY (organization_id)
- KEY (created_by_user_id)
- FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
- FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
```

### Lineup Artists Table (Pivot)
```sql
- id (bigint unsigned, primary key, auto-increment)
- lineup_id (bigint unsigned, foreign key -> lineups.id)
- artist_id (bigint unsigned, foreign key -> artists.id)
- tier (enum: 'headliner', 'sub_headliner', 'mid_tier', 'undercard') -- current tier
- suggested_tier (enum: 'headliner', 'sub_headliner', 'mid_tier', 'undercard') -- algorithm suggestion
- tier_override (boolean, default false) -- true if user manually changed tier
- position (integer, nullable) -- for manual ordering within tier
- added_at (timestamp, default CURRENT_TIMESTAMP)

Indexes:
- PRIMARY KEY (id)
- UNIQUE KEY (lineup_id, artist_id) -- prevent duplicate artists
- KEY (lineup_id)
- KEY (tier)
- KEY (tier_override) -- filter manual vs auto placements
- FOREIGN KEY (lineup_id) REFERENCES lineups(id) ON DELETE CASCADE
- FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE

Tier Override Behavior:
- When artist added: tier = suggested_tier, tier_override = false
- User drags to new tier: tier = new_tier, tier_override = true
- When lineup changes: recalculate suggested_tier for all artists
  - If tier_override = false: update tier to new suggested_tier
  - If tier_override = true: keep current tier, only update suggested_tier
```

---

## API Endpoints (Laravel Routes)

### Organization Endpoints (Inertia Pages)
- `GET /organizations/{id}/settings` - Organization settings page (Inertia)
- `GET /organizations/{id}/members` - Manage members page (Inertia)

### Organization API Endpoints
- `GET /api/organizations/{id}/metrics` - List all metric weights for organization
- `PUT /api/organizations/{id}/metrics/{metric_name}` - Update single metric weight
- `POST /api/organizations/{id}/metrics/preset` - Apply preset (balanced, streaming_focused, etc.)
- `POST /api/organizations/{id}/metrics` - Add new metric (e.g., instagram_followers)
- `DELETE /api/organizations/{id}/metrics/{metric_name}` - Disable metric
- `POST /api/organizations/{id}/members` - Invite member
- `DELETE /api/organizations/{id}/members/{userId}` - Remove member
- `PUT /api/organizations/{id}/members/{userId}/role` - Update member role

### Artist Endpoints
- `GET /api/artists` - List all artists (paginated)
- `GET /api/artists/{id}` - Get single artist with metrics
- `GET /api/artists/search?q={query}` - Search artists
- `POST /api/artists/{id}/refresh` - Refresh artist data

### Lineup Endpoints
- `GET /api/lineups` - List organization's lineups
- `POST /api/lineups` - Create new lineup (in current organization)
- `GET /api/lineups/{id}` - Get lineup with artists and tier suggestions
- `PUT /api/lineups/{id}` - Update lineup details
- `DELETE /api/lineups/{id}` - Delete lineup
- `POST /api/lineups/{id}/artists` - Add artist to lineup (auto-calculates suggested tier)
- `DELETE /api/lineups/{id}/artists/{artistId}` - Remove artist from lineup
- `PUT /api/lineups/{id}/artists/{artistId}/tier` - Manually override artist tier (sets tier_override=true)
- `POST /api/lineups/{id}/reset-tiers` - Reset all artists to suggested tiers (clears overrides)

---

## User Flows

### 1. Browse Artists Flow
1. User lands on dashboard (`GET /dashboard`)
2. **Inertia** renders `Dashboard.vue` with initial artist data (SSR)
3. Vue component displays artists from props
4. User types in search box → debounced API call to `GET /api/artists/search`
5. Vue updates artist list with API response (no page reload)

### 2. Create Lineup Flow
1. User clicks "Create Lineup" button
2. Inertia `<Form>` component submits to `POST /api/lineups`
3. Laravel creates lineup record in database
4. Returns JSON with new lineup ID
5. **Inertia navigates** to `GET /lineups/{id}` (page load with lineup data)
6. Lineup builder page rendered with Vue components

### 3. Add Artist to Lineup Flow
1. User searches for artist in lineup builder
2. Vue component debounces search, calls `GET /api/artists/search?q={query}`
3. Laravel queries database with Laravel Scout (full-text search)
4. API returns top 20 results as JSON
5. User selects artist from dropdown
6. Vue calls `POST /api/lineups/{id}/artists` with artist_id
7. Laravel adds to `lineup_artists` table
8. **TierCalculationService** recalculates all tiers for the lineup
9. API returns updated lineup with new tier assignments (JSON)
10. Vue updates UI reactively with new tiers (smooth animation, no page reload)

### 4. View Lineup Flow
1. User clicks lineup from list (`GET /lineups/{id}`)
2. **Inertia** loads lineup page with server-side data
3. Vue renders lineup builder with artists grouped by tier
4. Interactive features (drag-and-drop, remove artist) use API calls
5. Tier labels update in real-time via API responses

---

## AI-Human Collaboration Guidelines

### For AI Agents (Claude)
- **Code Generation:** Use Laravel Eloquent patterns, Inertia.js patterns, Vue Composition API with `<script setup>`
- **API Design:** Follow RESTful conventions, return JSON API resources
- **Testing:** Write Pest/PHPUnit tests for Laravel backend, Vue component logic
- **Naming:** Use Laravel conventions (snake_case for DB, camelCase for JS)
- **Documentation:** Add PHPDoc blocks, JSDoc comments for complex functions
- **Architecture:** Use Inertia for page navigation, API calls for interactivity
- **Service Layer:** Always delegate business logic to service classes (never in controllers)

### For Human Developers
- **Review:** Validate business logic in scoring algorithms and tier calculations
- **UX Design:** Provide wireframes/mockups for Vue/Inertia pages
- **API Keys:** Configure Spotify and YouTube API credentials in `.env`
- **Deployment:** Set up Laravel Cloud for monolithic deployment
- **QA:** Manual testing of edge cases, cross-browser compatibility
- **Scoring Weights:** Approve/adjust algorithm weights in `config/artist-tree.php`

---

## Non-Functional Requirements

### Performance
- API response time: < 2 seconds (95th percentile)
- Page load time: < 3 seconds
- Support 100 concurrent users

### Security
- Laravel authentication via Fortify/Sanctum (cookie-based)
- Laravel authorization policies for lineup ownership
- Rate limiting on API endpoints (60 req/min per user)
- Input validation via Form Request classes
- API key security (Spotify/YouTube in .env, never exposed to frontend)

### Scalability
- Laravel Cloud automatic scaling
- Managed database scaling with Laravel Cloud
- Asset optimization via Vite (code splitting, lazy loading)
- Cache layer (Redis) for external API responses

### Reliability
- 99.5% uptime target
- Automated error tracking (Sentry)
- Database backups (Laravel Cloud automatic)

---

## Success Metrics

1. **Technical Metrics:**
   - API error rate < 1%
   - Average API response time < 1s
   - Zero critical security vulnerabilities

2. **User Metrics:**
   - Users can create a lineup in < 5 minutes
   - 80% task completion rate
   - < 5% bounce rate on artist search

3. **Data Quality:**
   - 95% of artists have complete metrics
   - Data freshness within 24 hours
   - < 5% failed API fetches

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| API rate limits (Spotify/YouTube) | High | Implement 24-hour caching, request throttling, exponential backoff, background queue jobs |
| Laravel Cloud resource limits | Medium | Monitor usage, optimize N+1 queries with eager loading, upgrade plan proactively |
| Scoring algorithm accuracy | High | Unit tests with edge cases, configurable weights in config file, user feedback loop |
| Data staleness | Medium | 24-hour cache TTL, manual "refresh metrics" button, automated daily job |
| Tier calculation performance | Medium | Optimize query (sort by score DESC), limit lineup size to 100 artists, cache results |

---

## Future Enhancements

1. **Public API:** Expose existing API with token authentication for third-party developers
2. **Mobile App:** Native iOS/Android app using existing RESTful API
3. **Machine Learning:** Predictive tier suggestions based on festival genre and historical data
4. **Social Features:** Share lineups publicly, collaborative editing with multiple users
5. **Analytics Dashboard:** Historical trends, genre distribution, artist popularity over time
6. **Advanced Search:** Filter by genre, popularity range, location, availability
7. **Integrations:** Ticketing platforms (Eventbrite), venue databases, booking agents

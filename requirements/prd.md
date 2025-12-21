# Artist-Tree Product Requirements Document

**Version:** 1.0
**Last Updated:** 2025-12-21
**Tech Stack:** Laravel + Vue.js + Supabase

---

## Executive Summary

Artist-Tree is a festival lineup builder and artist discovery platform that helps music festival organizers, promoters, and enthusiasts create balanced lineups by providing data-driven insights on artist popularity, engagement metrics, and tier classifications.

---

## Technical Architecture

### Backend: Laravel (PHP)
- **Framework:** Laravel 12.x
- **Purpose:** RESTful API server, business logic, external API orchestration
- **Key Features:**
  - Eloquent ORM for data modeling
  - API resource transformers for JSON responses
  - Job queues for async API data fetching
  - Service layer for external API integration (Spotify, YouTube)
  - Cache layer for API response optimization

### Frontend: Vue.js 3
- **Framework:** Vue 3 with Composition API
- **Purpose:** Single Page Application (SPA) for user interface
- **Key Features:**
  - Component-based architecture with `<script setup>`
  - Reactive state management with `ref()` and `reactive()`
  - Composables for reusable logic
  - Vue Router for client-side routing
  - Pinia/Vuex for global state (if needed)

### Database: Supabase (PostgreSQL)
- **Platform:** Supabase (Postgres-based BaaS)
- **Purpose:** Primary data storage, real-time capabilities, authentication
- **Key Features:**
  - PostgreSQL database with row-level security
  - Real-time subscriptions for live updates
  - Authentication and authorization
  - Auto-generated REST API
  - Storage for media/assets

---

## Core Features & Business Requirements

### 1. Artist Data Aggregation
**Business Need:** Provide comprehensive, up-to-date artist metrics from multiple sources

**Technical Implementation:**
- Laravel services to fetch data from Spotify API and YouTube API
- Supabase database tables: `artists`, `artist_metrics`, `artist_spotify_data`, `artist_youtube_data`
- Caching layer to minimize API calls and stay within rate limits
- Background jobs for periodic data refresh

**Success Criteria:**
- Artist data refreshed within 24 hours of request
- < 2 second response time for cached data
- 99% API call success rate with retry logic

### 2. Artist Scoring Algorithm
**Business Need:** Objectively rank artists based on quantifiable metrics

**Technical Implementation:**
- Laravel service class for score calculation
- Algorithm considers: Spotify monthly listeners, YouTube subscribers, engagement rates
- Stored in `artist_metrics` table with timestamp
- Unit tested with various artist profiles

**Success Criteria:**
- Scores normalized to 0-100 scale
- Reproducible results for same input data
- Algorithm adjustable via configuration

### 3. Lineup Builder
**Business Need:** Allow users to create festival lineups with automatic tier assignment

**Technical Implementation:**
- Supabase tables: `lineups`, `lineup_artists`
- Laravel API endpoints for CRUD operations
- Vue.js drag-and-drop interface
- Real-time tier recalculation on artist addition/removal

**Success Criteria:**
- Support 50+ artists per lineup
- Tier assignments update in < 1 second
- Lineups persist across sessions

### 4. Tier Classification
**Business Need:** Automatically categorize artists into festival tiers (Headliner, Sub-Headliner, Mid-Tier, Undercard)

**Technical Implementation:**
- Dynamic tier calculation based on relative scores within lineup
- Configurable tier thresholds
- Visual representation in Vue components

**Success Criteria:**
- Clear tier boundaries with no overlap
- Minimum of 1 headliner per lineup
- Tiers rebalance when artists added/removed

### 5. Search & Discovery
**Business Need:** Enable users to find and add artists efficiently

**Technical Implementation:**
- Full-text search in Supabase
- Vue.js search component with debouncing
- Autocomplete suggestions
- Filter by genre, popularity range

**Success Criteria:**
- Search results appear in < 500ms
- Support partial name matching
- Return top 20 relevant results

---

## Data Models (Supabase Schema)

### Artists Table
```sql
- id (uuid, primary key)
- name (text)
- spotify_id (text, unique)
- youtube_channel_id (text)
- genre (text[])
- image_url (text)
- created_at (timestamp)
- updated_at (timestamp)
```

### Artist Metrics Table
```sql
- id (uuid, primary key)
- artist_id (uuid, foreign key)
- score (numeric)
- spotify_monthly_listeners (bigint)
- youtube_subscribers (bigint)
- engagement_rate (numeric)
- last_refreshed (timestamp)
```

### Lineups Table
```sql
- id (uuid, primary key)
- user_id (uuid, foreign key)
- name (text)
- description (text)
- created_at (timestamp)
- updated_at (timestamp)
```

### Lineup Artists Table
```sql
- id (uuid, primary key)
- lineup_id (uuid, foreign key)
- artist_id (uuid, foreign key)
- tier (text) -- headliner, sub_headliner, mid_tier, undercard
- position (integer)
- added_at (timestamp)
```

---

## API Endpoints (Laravel Routes)

### Artist Endpoints
- `GET /api/artists` - List all artists (paginated)
- `GET /api/artists/{id}` - Get single artist with metrics
- `GET /api/artists/search?q={query}` - Search artists
- `POST /api/artists/{id}/refresh` - Refresh artist data

### Lineup Endpoints
- `GET /api/lineups` - List user's lineups
- `POST /api/lineups` - Create new lineup
- `GET /api/lineups/{id}` - Get lineup with artists
- `PUT /api/lineups/{id}` - Update lineup details
- `DELETE /api/lineups/{id}` - Delete lineup
- `POST /api/lineups/{id}/artists` - Add artist to lineup
- `DELETE /api/lineups/{id}/artists/{artistId}` - Remove artist from lineup

---

## User Flows

### 1. Browse Artists Flow
1. User lands on dashboard
2. Vue component fetches artists from Laravel API
3. Laravel queries Supabase via Eloquent
4. Data transformed via API resources
5. Vue renders artist table with metrics

### 2. Create Lineup Flow
1. User clicks "Create Lineup"
2. Vue form submits to Laravel API
3. Laravel creates record in Supabase
4. Returns lineup ID to Vue
5. Vue navigates to lineup builder

### 3. Add Artist to Lineup Flow
1. User searches for artist in lineup builder
2. Vue component debounces search, calls Laravel API
3. Laravel queries Supabase with full-text search
4. User selects artist
5. Laravel adds to lineup_artists, recalculates tiers
6. Vue updates UI with new tier assignments

---

## AI-Human Collaboration Guidelines

### For AI Agents (Claude)
- **Code Generation:** Use Laravel Eloquent patterns, Vue Composition API with `<script setup>`
- **API Design:** Follow RESTful conventions, return JSON API resources
- **Testing:** Write PHPUnit tests for Laravel, Vitest tests for Vue components
- **Naming:** Use Laravel conventions (snake_case for DB, camelCase for JS)
- **Documentation:** Add PHPDoc blocks, JSDoc comments for complex functions

### For Human Developers
- **Review:** Validate business logic in scoring algorithms and tier calculations
- **UX Design:** Provide wireframes/mockups for Vue components
- **API Keys:** Configure Spotify and YouTube API credentials
- **Deployment:** Set up Vercel (frontend), Laravel hosting, Supabase project
- **QA:** Manual testing of edge cases, cross-browser compatibility

---

## Non-Functional Requirements

### Performance
- API response time: < 2 seconds (95th percentile)
- Page load time: < 3 seconds
- Support 100 concurrent users

### Security
- Supabase row-level security policies
- Laravel API authentication via Sanctum/Passport
- CORS configuration for Vue SPA
- Rate limiting on API endpoints

### Scalability
- Horizontal scaling via stateless Laravel API
- Supabase managed database scaling
- CDN for Vue.js static assets

### Reliability
- 99.5% uptime target
- Automated error tracking (Sentry)
- Database backups (Supabase automatic)

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
| API rate limits (Spotify/YouTube) | High | Implement caching, request throttling, background jobs |
| Supabase free tier limits | Medium | Monitor usage, upgrade plan proactively |
| Vue.js bundle size | Low | Code splitting, lazy loading, tree shaking |
| Data staleness | Medium | Automated refresh jobs, manual refresh option |
| Scoring algorithm accuracy | High | A/B testing, user feedback loop, configurable weights |

---

## Future Enhancements

1. **Machine Learning:** Predictive tier suggestions based on festival genre
2. **Social Features:** Share lineups, collaborative editing
3. **Analytics Dashboard:** Historical trends, genre analysis
4. **Mobile App:** React Native or Flutter app
5. **Integrations:** Ticketing platforms, venue databases

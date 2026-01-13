# Project Structure & Organization

## Root Directory Layout

```
├── app/                    # Laravel application code
├── resources/              # Frontend assets (Vue/TypeScript)
├── database/               # Migrations, seeders, factories
├── tests/                  # Test suites (Unit, Feature, Browser)
├── config/                 # Laravel configuration files
├── routes/                 # Route definitions
├── public/                 # Web server document root
├── storage/                # File storage, logs, cache
├── bootstrap/              # Framework bootstrap files
├── requirements/           # Project documentation & data
├── scripts/                # Utility scripts
└── .kiro/                  # Kiro AI assistant configuration
```

## Backend Structure (Laravel)

### Application Layer (`app/`)
```
app/
├── Actions/                # Fortify authentication actions
├── Console/Commands/       # Artisan commands (artist import, etc.)
├── DataTransferObjects/    # API response DTOs (Spotify, YouTube)
├── Enums/                  # Type-safe enumerations
├── Exceptions/             # Custom exception classes
├── Http/
│   ├── Controllers/        # Thin controllers (delegate to services)
│   ├── Middleware/         # Request/response middleware
│   ├── Requests/           # Form validation classes
│   └── Resources/          # API response transformers
├── Jobs/                   # Background job classes
├── Models/                 # Eloquent models
├── Policies/               # Authorization policies
└── Services/               # Business logic layer (REQUIRED)
```

### Key Service Classes (Business Logic)
All business logic MUST be in service classes, not controllers:

- `ArtistScoringService` - Calculate artist scores (organization-aware)
- `ArtistSearchService` - Artist discovery and creation
- `SpotifyService` - Spotify API integration with caching
- `YouTubeService` - YouTube API integration with caching
- `TierCalculationService` - Lineup tier assignments
- `LineupService` - Lineup management operations

### Model Relationships
- **Organization** ↔ **User** (many-to-many with roles)
- **Organization** → **MetricWeight** (one-to-many, custom scoring weights)
- **Organization** → **Lineup** (one-to-many)
- **Lineup** ↔ **Artist** (many-to-many via LineupArtist pivot)
- **Artist** → **ArtistMetric** (one-to-many, time-series data)

## Frontend Structure (Vue 3 + TypeScript)

### Resources Directory (`resources/`)
```
resources/
├── css/
│   └── app.css             # Tailwind CSS entry point
└── js/
    ├── app.ts              # Application entry point
    ├── ssr.ts              # Server-side rendering entry
    ├── components/         # Vue components
    │   ├── ui/             # Reusable UI components (shadcn-style)
    │   ├── artist/         # Artist-specific components
    │   ├── lineup/         # Lineup management components
    │   ├── dashboard/      # Dashboard widgets
    │   └── settings/       # Settings/configuration components
    ├── composables/        # Vue composition functions
    ├── layouts/            # Page layout components
    ├── pages/              # Inertia.js page components
    ├── types/              # TypeScript type definitions
    └── lib/                # Utility functions
```

### Component Organization Patterns
- **UI Components**: Reusable, generic components in `components/ui/`
- **Feature Components**: Domain-specific components grouped by feature
- **Page Components**: Top-level Inertia.js pages in `pages/`
- **Layout Components**: Shared layouts in `layouts/`

### TypeScript Conventions
- Use Vue 3 Composition API with `<script setup lang="ts">`
- Define props with `defineProps<T>()` interface syntax
- Use composables for shared logic (prefix with `use`)
- Type external API responses with DTOs

## Database Structure

### Migrations (`database/migrations/`)
- Follow Laravel timestamp naming: `YYYY_MM_DD_HHMMSS_description.php`
- Use descriptive names that indicate the change type
- Always include rollback logic in `down()` method

### Key Tables
- `users` - User authentication (Laravel Fortify)
- `organizations` - Multi-tenant organizations
- `organization_user` - User-organization relationships with roles
- `artists` - Artist master data with Spotify/YouTube IDs
- `artist_metrics` - Time-series metrics data
- `lineups` - Festival lineups (belongs to organization)
- `lineup_artists` - Lineup-artist relationships with tier data
- `metric_weights` - Organization-specific scoring weights

## Testing Structure

### Test Organization (`tests/`)
```
tests/
├── Unit/                   # Isolated unit tests
│   ├── Models/             # Model tests
│   ├── Services/           # Service class tests
│   └── Jobs/               # Background job tests
├── Feature/                # Integration tests
│   ├── Api/                # API endpoint tests
│   ├── Auth/               # Authentication flow tests
│   └── [Feature]/          # Feature-specific test groups
└── Browser/                # End-to-end browser tests (Playwright)
```

### Testing Conventions
- Use Pest PHP syntax for all backend tests
- Browser tests use Playwright for E2E scenarios
- Feature tests use SQLite in-memory for speed
- Mock external APIs (Spotify, YouTube) in tests
- Test organization-aware features with multiple organizations

## Configuration Files

### Laravel Config (`config/`)
- `artist-tree.php` - Custom application configuration
  - Metric normalization max values
  - Default metric weight presets
  - API rate limiting settings
- `services.php` - External API credentials (Spotify, YouTube)
- Standard Laravel configs (database, cache, queue, etc.)

### Frontend Config
- `vite.config.ts` - Vite build configuration
- `tsconfig.json` - TypeScript compiler options
- `eslint.config.js` - ESLint rules for Vue/TypeScript
- `.prettierrc` - Code formatting rules
- `tailwind.config.js` - Tailwind CSS configuration

## File Naming Conventions

### Backend (Laravel)
- **Controllers**: `PascalCase` + `Controller` suffix
- **Models**: `PascalCase` (singular)
- **Services**: `PascalCase` + `Service` suffix
- **Jobs**: `PascalCase` + `Job` suffix
- **Requests**: `PascalCase` + `Request` suffix
- **Resources**: `PascalCase` + `Resource` suffix

### Frontend (Vue/TypeScript)
- **Components**: `PascalCase.vue`
- **Pages**: `PascalCase.vue` (Inertia.js pages)
- **Composables**: `camelCase.ts` with `use` prefix
- **Types**: `camelCase.ts` or `index.d.ts`
- **Utilities**: `camelCase.ts`

## Import/Export Patterns

### Backend
- Use Laravel's auto-discovery for service providers
- Services injected via constructor dependency injection
- Models use Eloquent relationships and scopes

### Frontend
- Use ES6 imports/exports
- Prefer named exports over default exports
- Group imports: external libraries, then internal modules
- Use path aliases defined in `vite.config.ts`

## Key Architectural Principles

1. **Thin Controllers**: All business logic in service classes
2. **Organization-Aware Services**: Multi-tenant design with organization context
3. **External API Abstraction**: Dedicated service classes with caching and rate limiting
4. **Type Safety**: Full TypeScript coverage on frontend, PHP 8.4 types on backend
5. **Test Coverage**: Unit tests for services, feature tests for workflows, browser tests for critical paths
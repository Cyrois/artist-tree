# Technology Stack & Build System

## Core Stack

- **Backend**: Laravel 12 (PHP 8.4+)
- **Frontend**: Inertia.js v2 + Vue 3 + TypeScript
- **Database**: PostgreSQL 17+ (Serverless PostgreSQL on Laravel Cloud)
- **Styling**: Tailwind CSS v4
- **Testing**: Pest PHP for backend, Browser tests with Playwright
- **Build Tool**: Vite
- **Package Managers**: Composer (PHP), npm (Node.js)

## Key Dependencies

### Backend (Laravel)
- `inertiajs/inertia-laravel` - SPA-like experience with server-side routing
- `laravel/fortify` - Authentication scaffolding
- `laravel/wayfinder` - Route management
- `laravel/boost` - Development tools
- `pestphp/pest` - Testing framework

### Frontend (Vue/TypeScript)
- `@inertiajs/vue3` - Inertia.js Vue adapter
- `reka-ui` - UI component library
- `lucide-vue-next` - Icon library
- `@vueuse/core` - Vue composition utilities
- `laravel-vue-i18n` - Internationalization
- `class-variance-authority` + `clsx` + `tailwind-merge` - Styling utilities

## Development Commands

### Initial Setup
```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup (PostgreSQL required)
php artisan migrate
php artisan db:seed  # Optional: seed with test data
```

### Development Servers
```bash
# Start all development services (recommended)
composer run dev

# Or manually start individual services:
php artisan serve          # Laravel server (port 8000)
npm run dev                # Vite dev server
php artisan queue:listen   # Background job processing
php artisan pail           # Real-time log monitoring
```

### Testing
```bash
# Run all tests
php artisan test
# or
composer run test

# Run specific test types
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test tests/Browser  # Browser/E2E tests
```

### Code Quality
```bash
# PHP formatting (Laravel Pint)
./vendor/bin/pint

# Frontend linting and formatting
npm run lint        # ESLint with auto-fix
npm run format      # Prettier formatting
npm run format:check # Check formatting without changes
```

### Build & Deployment
```bash
# Production build
npm run build

# SSR build (if using server-side rendering)
npm run build:ssr

# Full setup script (production)
composer run setup
```

## External API Integration

### Required Environment Variables
```env
# Spotify API (required for artist data)
SPOTIFY_CLIENT_ID=your_spotify_client_id
SPOTIFY_CLIENT_SECRET=your_spotify_client_secret

# YouTube API (required for subscriber data)
YOUTUBE_API_KEY=your_youtube_api_key
```

### API Rate Limits & Caching
- Spotify: 180 requests/minute, cache responses for 24 hours
- YouTube: 10,000 units/day quota, cache responses for 24 hours
- All external API calls are handled through dedicated service classes with built-in rate limiting

## Database Configuration

### Local Development (PostgreSQL)
```bash
# macOS with Homebrew
brew install postgresql@17
brew services start postgresql@17
createdb artist_tree
```

### Testing (SQLite in-memory)
Tests automatically use SQLite in-memory for speed. No additional setup required.

## Code Style & Conventions

- **PHP**: Follow Laravel conventions, use Laravel Pint for formatting
- **TypeScript/Vue**: ESLint + Prettier with Vue 3 Composition API
- **CSS**: Tailwind CSS v4 with utility-first approach
- **Indentation**: 4 spaces for PHP/Vue/CSS, 2 spaces for YAML
- **Line endings**: LF (Unix-style)
- **Trailing whitespace**: Trimmed (except in Markdown)
# Artist Tree

A festival lineup builder and artist discovery platform that helps music festival organizers create balanced lineups using data-driven insights from Spotify and YouTube APIs.

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** Inertia.js v2 + Vue 3
- **Database:** PostgreSQL (Serverless PostgreSQL on Laravel Cloud)
- **Styling:** Tailwind CSS v4
- **Testing:** Pest PHP

## Requirements

- PHP 8.4+
- Node.js 20+
- PostgreSQL 17+
- Composer

## Local Development Setup

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

Install and start PostgreSQL:

```bash
# macOS with Homebrew
brew install postgresql@17
brew services start postgresql@17

# Create the database
createdb artist_tree
```

Update your `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=artist_tree
DB_USERNAME=your_username
DB_PASSWORD=
```

Run migrations:

```bash
php artisan migrate
```

### 5. Seed Data (Optional)

You can seed the database with artist data from CSV files and enrich them via Spotify:

```bash
# Import artists from CSV files
php artisan artist:import-csv requirements/data/*.csv

# Hydrate imported artists with Spotify metadata and metrics
php artisan artist:hydrate-spotify
```

### 6. Start Development Servers

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev
```

Visit http://localhost:8000

## Testing

Tests use SQLite in-memory for speed:

```bash
php artisan test
```

## Production (Laravel Cloud)

The application is deployed to Laravel Cloud with:
- Serverless PostgreSQL database
- Automatic migrations via deploy hooks

### Deploy Hook

```bash
php artisan migrate --force
```

## API Keys

Required external API credentials (add to `.env`):

- `SPOTIFY_CLIENT_ID` - From [Spotify Developer Dashboard](https://developer.spotify.com/dashboard)
- `SPOTIFY_CLIENT_SECRET`
- `YOUTUBE_API_KEY` - From [Google Cloud Console](https://console.cloud.google.com)

## Documentation

- Project guidelines: `CLAUDE.md`
- Backend patterns: `.claude/guidelines/backend-patterns.md`
- Frontend patterns: `.claude/guidelines/frontend-patterns.md`
- Testing patterns: `.claude/guidelines/testing-patterns.md`

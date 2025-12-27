# Development Workflow & Tooling

## Before Writing Code

### 1. Check Existing Patterns

Review sibling files for established conventions:

- **Controllers:** Check existing controllers for validation pattern (array vs string rules)
- **Components:** Look for reusable Vue components before creating new ones
- **Migrations:** Follow existing column naming and type conventions
- **Models:** Check casting patterns (`$casts` property vs `casts()` method)

### 2. Search Documentation

Use `search-docs` MCP tool for version-specific guidance:

```
search-docs queries=["rate limiting", "routing"]
```

**Best Practices:**
- Pass multiple broad queries: `['rate limiting', 'routing rate limiting', 'routing']`
- Don't include package names (already passed automatically)
- Examples:
  - For Inertia forms: `['form component', 'useForm helper']`
  - For Laravel validation: `['validation', 'form requests']`
  - For Pest testing: `['browser testing', 'datasets']`

### 3. Run Existing Tests

Verify current functionality before making changes:

```bash
php artisan test --filter=relevant_test_name
```

---

## During Development

### Laravel Artisan Commands

**Generate Files:**

```bash
# List all available commands and parameters
php artisan list

# Create model with migration, factory, seeder
php artisan make:model Artist -mfs

# Create controller with resources
php artisan make:controller ArtistController --resource

# Create Form Request
php artisan make:request StoreLineupRequest

# Create Service class
php artisan make:class Services/ArtistScoringService

# Create Pest test
php artisan make:test ArtistScoringTest --pest --unit
```

**Always pass `--no-interaction`** when using Artisan commands in automated contexts.

### Laravel Boost MCP Tools

**Critical Tools:**

1. **`search-docs`** - Search version-specific documentation
   ```
   search-docs queries=["authentication", "middleware"]
   ```

2. **`list-artisan-commands`** - Check available Artisan commands
   ```
   list-artisan-commands
   ```

3. **`tinker`** - Execute PHP for debugging
   ```php
   tinker code="Artist::where('spotify_id', 'test-123')->first()"
   ```

4. **`database-query`** - Read from database
   ```sql
   database-query query="SELECT * FROM artists LIMIT 10"
   ```

5. **`get-absolute-url`** - Get correct project URL
   ```
   get-absolute-url path="/lineups/123"
   ```

6. **`browser-logs`** - Read browser errors (only recent logs useful)
   ```
   browser-logs entries=20
   ```

### Frontend Development

**If changes aren't reflected in browser:**

Ask user to run one of:

```bash
npm run dev          # Development mode with hot reload
npm run build        # Production build
composer run dev     # Laravel wrapper for npm run dev
```

**Vite Manifest Error:**

```
Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest
```

**Solution:** Run `npm run build` or ask user to start dev server

### Code Quality Tools

**Laravel Pint (Code Formatting):**

```bash
vendor/bin/pint --dirty  # Format only changed files
vendor/bin/pint          # Format all files
```

**Do NOT run** `vendor/bin/pint --test` (only checks, doesn't fix)

**Run Pint before finalizing ALL code changes.**

---

## After Writing Code

### 1. Write/Update Tests

Create or update Pest tests for new functionality:

```bash
# Feature test
php artisan make:test Lineups/CreateLineupTest --pest

# Unit test
php artisan make:test Services/ArtistScoringServiceTest --pest --unit
```

**Test Coverage Requirements:**
- Every new feature MUST have tests
- Test happy paths, failure paths, and edge cases
- Use factories for test data
- Mock external APIs (Spotify, YouTube)

### 2. Run Tests

**Run minimal tests first:**

```bash
# Run specific test file
php artisan test tests/Feature/Lineups/CreateLineupTest.php

# Filter by test name
php artisan test --filter=creates_lineup_with_valid_data

# Run all tests in a directory
php artisan test tests/Unit/Services
```

**After targeted tests pass, ask user:**

> "The tests for this feature are passing. Would you like me to run the full test suite to ensure nothing broke?"

### 3. Format Code

**Always run Pint:**

```bash
vendor/bin/pint --dirty
```

### 4. Check for N+1 Queries

If Laravel Debugbar is installed:
- Load the page
- Check "Queries" panel
- Look for duplicate queries

**Prevention:**

```php
// ✅ GOOD: Eager loading
$lineup = Lineup::with('artists.metrics')->find($id);

// ❌ BAD: N+1 problem
$lineup = Lineup::find($id);
foreach ($lineup->artists as $artist) {
    echo $artist->metrics->score;
}
```

---

## Git Commit Messages

Use conventional commits:

```
feat: add artist scoring algorithm with Spotify integration
fix: prevent duplicate artists in lineup
refactor: extract tier calculation into service class
test: add edge cases for scoring algorithm
docs: update API endpoint documentation
```

**Format:**

```
<type>: <description>

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `refactor`: Code restructuring without behavior change
- `test`: Add or update tests
- `docs`: Documentation only
- `style`: Code style/formatting
- `perf`: Performance improvement
- `chore`: Maintenance tasks

---

## Debugging Workflow

### Backend Issues

1. Check Laravel logs: `storage/logs/laravel.log`
2. Use `tinker` to test Eloquent queries
3. Use `database-query` to inspect data
4. Add `dd()` or `dump()` in controller/service
5. Check test output for assertion failures

### Frontend Issues

1. Use `browser-logs` tool to read console errors
2. Check browser DevTools Network tab for API errors
3. Inspect Vue DevTools for component state
4. Add `console.log()` for debugging (remove before commit)

### API Issues

1. Check API response in Network tab
2. Verify request payload matches Form Request validation
3. Check authorization policies
4. Test endpoint with `php artisan test --filter=endpoint_name`

---

## Quality Checklist

Before considering work complete:

- [ ] Searched documentation using `search-docs`
- [ ] Followed existing code patterns from sibling files
- [ ] Used `php artisan make:` commands for file generation
- [ ] Created/updated Pest tests
- [ ] Tests pass with `php artisan test --filter=...`
- [ ] Code formatted with `vendor/bin/pint --dirty`
- [ ] Checked for N+1 queries
- [ ] No `env()` usage outside config files
- [ ] Used explicit return types on all methods
- [ ] Used Form Request classes for validation
- [ ] Followed Laravel 12 structure (no Kernel.php, etc.)
- [ ] Added authorization checks (policies)
- [ ] Handled errors gracefully
- [ ] Removed debugging code (`dd()`, `console.log()`)

---

## Common Pitfalls to Avoid

### 1. Using `env()` Outside Config Files

```php
// ❌ BAD
$appName = env('APP_NAME');

// ✅ GOOD
$appName = config('app.name');
```

### 2. Inline Validation in Controllers

```php
// ❌ BAD
$validated = $request->validate(['name' => 'required']);

// ✅ GOOD
// Use Form Request classes
public function store(StoreLineupRequest $request)
{
    // Validation already done
}
```

### 3. Missing Eager Loading

```php
// ❌ BAD: N+1 query problem
$lineup->artists->each(fn($a) => $a->metrics);

// ✅ GOOD
$lineup->load('artists.metrics');
```

### 4. Forgetting Organization Context

```php
// ❌ BAD: Missing organization
$score = $service->calculateScore($artist);

// ✅ GOOD
$score = $service->calculateScore($artist, $organization);
```

### 5. Not Mocking External APIs in Tests

```php
// ❌ BAD: Actually calling Spotify API
$response = $spotify->searchArtist('test');

// ✅ GOOD
Http::fake(['api.spotify.com/*' => Http::response([...])]);
```

### 6. Creating New Base Folders Without Approval

Follow existing `app/Services/`, `app/Policies/` structure

### 7. Removing Existing Tests

Tests are NEVER temporary - don't remove without approval

---

## Performance Optimization

### Response Time Targets

- Cached artist data: <500ms
- Artist search: <500ms
- Lineup tier recalculation: <1 second
- External API calls: <2 seconds (with caching)

### Caching Strategy

- Spotify access token: 1 hour (Redis/file cache)
- Artist search results: 24 hours
- Artist metrics: 24 hours (force refresh via button)
- YouTube channel data: 24 hours

### Database Query Optimization

- **Eager load relationships:** `Lineup::with('artists.metrics')->find($id)`
- **Paginate artist lists:** 50 per page
- **Index all foreign keys** and search columns

---

For tool-specific guidelines, see `.gemini/guidelines/laravel-boost.md`.

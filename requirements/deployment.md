# Laravel Cloud Deployment Guide

## 📋 Complete Deployment Instructions for Artist Tree

This guide covers the complete deployment process for the Artist Tree application to Laravel Cloud.

---

## Phase 1: Prepare Your Application ✅

### Step 1.1: Environment Variables ✅

The `.env.example` file has been configured with production-ready settings:
- Database: SQLite → MySQL
- Cache: database → Redis
- Added Spotify & YouTube API key placeholders
- Set `APP_DEBUG=false` for production

### Step 1.2: Push Your Changes

```bash
# Make sure you're on your main branch
git checkout main

# Merge your current work (if you want to deploy ui-mockup branch)
git merge ui-mockup

# Or just push your current branch
git push origin ui-mockup
```

---

## Phase 2: Sign Up for Laravel Cloud

### Step 2.1: Create Account
1. Visit **https://cloud.laravel.com**
2. Click **"Sign up"** in the top navigation
3. Create your account (email + password or OAuth)

### Step 2.2: Pricing Plan
- **Starter Plan**: No monthly fee - pay only for usage
- Apps auto-hibernate when idle (zero cost)
- Free SSL certificates
- **Recommended for MVP**: Start with Starter plan

---

## Phase 3: Create Your First Project

### Step 3.1: Connect Git Repository

1. In Laravel Cloud dashboard, click **"Create New Application"**
2. **Connect Git Provider**:
   - Select your Git provider (GitHub, GitLab, Bitbucket)
   - Authorize Laravel Cloud to access your repositories
3. **Select Repository**: Choose `artist-tree`
4. **Select Branch**: Choose `main` (or `ui-mockup` if you want to deploy that)

### Step 3.2: Configure Application

1. **Application Name**: `artist-tree` (or your preferred name)
2. **Region**: Select closest to your users (US East, US West, EU, etc.)
3. **PHP Version**: Select **8.2** (your app requires `^8.2`)

---

## Phase 4: Configure Environment & Resources

### Step 4.1: Database Setup

Laravel Cloud will prompt you to add a database:

1. Click **"Add Database"**
2. Choose **MySQL 8.0**
3. Select instance size:
   - **Development**: Small (512MB RAM)
   - **Production**: Medium (1GB RAM) or larger
4. Database credentials are **auto-injected** into your app's environment

### Step 4.2: Redis Cache Setup

1. Click **"Add Cache"**
2. Choose **Redis**
3. Select instance size (Small for MVP)
4. Credentials auto-injected as `REDIS_HOST`, `REDIS_PASSWORD`, etc.

### Step 4.3: Environment Variables

Set these in the Laravel Cloud dashboard under **Settings → Environment**:

```bash
# App Configuration
APP_NAME="Artist Tree"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-name.cloud.laravel.app  # Laravel Cloud provides this

# Database (auto-populated by Laravel Cloud)
DB_CONNECTION=mysql
DB_HOST=<auto-filled>
DB_PORT=<auto-filled>
DB_DATABASE=<auto-filled>
DB_USERNAME=<auto-filled>
DB_PASSWORD=<auto-filled>

# Cache & Queue
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Redis (auto-populated by Laravel Cloud)
REDIS_HOST=<auto-filled>
REDIS_PASSWORD=<auto-filled>
REDIS_PORT=<auto-filled>

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# External APIs (YOU MUST ADD THESE)
SPOTIFY_CLIENT_ID=your_spotify_client_id
SPOTIFY_CLIENT_SECRET=your_spotify_client_secret
YOUTUBE_API_KEY=your_youtube_api_key

# Mail (configure later)
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=hello@artist-tree.com
MAIL_FROM_NAME="Artist Tree"
```

**Important**: Laravel Cloud auto-generates `APP_KEY` - no need to set it manually.

---

## Phase 5: Configure Build & Deploy Commands

In Laravel Cloud dashboard under **Settings → Deployments**:

### Build Commands:
```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

### Deploy Commands:
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Note**:
- `--force` flag required for production migrations (no prompt)
- These commands run on **every deployment**

---

## Phase 6: Deploy Your Application

### Step 6.1: Trigger First Deployment

**Option 1 - Automatic (Push to Deploy)**:
```bash
# Push to your connected branch
git push origin main
```

Laravel Cloud automatically detects the push and starts deployment.

**Option 2 - Manual**:
1. Go to Laravel Cloud dashboard
2. Click **"Deploy Now"** button
3. Select branch/commit to deploy

### Step 6.2: Monitor Deployment

1. Watch the build logs in real-time in the dashboard
2. Deployment typically takes **1-3 minutes**:
   - Building Docker image (~30s)
   - Installing dependencies (~1min)
   - Running build commands (~30s)
   - Running migrations (~10s)
   - Zero-downtime swap (~5s)

### Step 6.3: Verify Deployment

Once complete, you'll see:
- ✅ Green "Active" status
- Your app URL: `https://your-app-name.cloud.laravel.app`
- Deployment timestamp and commit hash

---

## Phase 7: Post-Deployment Setup

### Step 7.1: Run Database Seeders (Optional)

If you have seeders, run via the Laravel Cloud CLI or dashboard terminal:

```bash
php artisan db:seed --force
```

### Step 7.2: Configure Custom Domain (Optional)

1. Go to **Settings → Domains**
2. Click **"Add Domain"**
3. Enter your domain (e.g., `artist-tree.com`)
4. Add DNS records provided by Laravel Cloud:
   - **A Record**: Point to provided IP
   - **CNAME**: Point `www` to provided URL
5. SSL certificate auto-provisions in ~2 minutes

### Step 7.3: Set Up Queue Workers

For background jobs (Spotify/YouTube API calls):

1. Go to **Settings → Workers**
2. Click **"Add Worker"**
3. Configure:
   - **Command**: `php artisan queue:work --tries=3 --timeout=90`
   - **Instances**: 1 (scale up later if needed)
   - **Memory**: 512MB

---

## Phase 8: Get Your API Keys

### Spotify API Keys

1. Visit **https://developer.spotify.com/dashboard**
2. Log in or create account
3. Click **"Create App"**
4. Fill in:
   - **App Name**: Artist Tree
   - **App Description**: Festival lineup builder
   - **Redirect URI**: `https://your-app.cloud.laravel.app/callback` (not needed for client credentials flow)
5. Copy **Client ID** and **Client Secret**
6. Add to Laravel Cloud environment variables

### YouTube Data API Key

1. Visit **https://console.cloud.google.com/**
2. Create new project or select existing
3. Enable **YouTube Data API v3**:
   - Go to **APIs & Services → Library**
   - Search "YouTube Data API v3"
   - Click **Enable**
4. Create credentials:
   - Go to **APIs & Services → Credentials**
   - Click **"Create Credentials" → API Key**
   - Copy the API key
5. **Restrict the key** (recommended):
   - Click on the key → **Application restrictions** → HTTP referrers
   - Add your domain: `https://your-app.cloud.laravel.app/*`
6. Add to Laravel Cloud environment variables

### Update Environment

After getting API keys:
1. Go to Laravel Cloud dashboard → **Settings → Environment**
2. Update the variables:
   ```
   SPOTIFY_CLIENT_ID=your_actual_client_id
   SPOTIFY_CLIENT_SECRET=your_actual_secret
   YOUTUBE_API_KEY=your_actual_api_key
   ```
3. Click **"Save & Deploy"** to redeploy with new keys

---

## Phase 9: Enable Auto-Scaling (Optional)

For production traffic:

1. Go to **Settings → Scaling**
2. Configure:
   - **Min Instances**: 1 (always-on) or 0 (auto-hibernate)
   - **Max Instances**: 3-5 (for MVP)
   - **CPU Threshold**: 70%
   - **Memory Threshold**: 80%

---

## 🔄 Ongoing Deployments

### Automatic Deployments
Every time you push to your connected branch:
```bash
git add .
git commit -m "Add new feature"
git push origin main  # Auto-deploys to Laravel Cloud
```

### Manual Deployments
- Use the **"Deploy Now"** button in dashboard
- Useful for deploying specific commits or rolling back

### Rollback
1. Go to **Deployments** tab
2. Find previous successful deployment
3. Click **"Redeploy"** to instantly rollback

---

## 📊 Monitoring & Logs

### View Application Logs
```bash
# In Laravel Cloud dashboard → Logs tab
# Real-time log streaming
# Filter by level: error, warning, info, debug
```

### Performance Monitoring
- Dashboard shows CPU, Memory, Request rate
- Set up alerts for errors/downtime
- Monitor database query performance

---

## 💰 Cost Optimization

### Starter Plan Costs
- **Compute**: ~$0.01/hour per instance (~$7/month if always-on)
- **Database**: ~$10-20/month (512MB-1GB instance)
- **Redis**: ~$5-10/month (small instance)
- **Storage**: Included
- **SSL**: Free
- **Auto-hibernation**: Scales to zero during inactivity

### Estimated MVP Monthly Cost: $20-40/month

---

## 🚨 Troubleshooting

### Build Fails
1. Check build logs in dashboard
2. Common issues:
   - Missing `package-lock.json` (run `npm install` locally and commit)
   - Composer dependency conflicts (run `composer update` locally)
   - Missing `.env` variables

### Migration Fails
1. Ensure `--force` flag in deploy commands
2. Check database credentials are set
3. Verify database is running

### Assets Not Loading
1. Ensure `npm run build` in build commands
2. Check Vite config is production-ready
3. Verify `APP_URL` matches your domain

---

## ✅ Quick Checklist

- [ ] Push code to GitHub/GitLab/Bitbucket
- [ ] Sign up at cloud.laravel.com
- [ ] Create new application and connect Git repo
- [ ] Add MySQL database
- [ ] Add Redis cache
- [ ] Set environment variables
- [ ] Configure build/deploy commands
- [ ] Get Spotify API credentials
- [ ] Get YouTube API credentials
- [ ] Deploy application
- [ ] Set up queue worker
- [ ] Configure custom domain (optional)
- [ ] Enable auto-scaling (optional)

---

## 📚 Additional Resources

- [Laravel Cloud Docs](https://cloud.laravel.com/docs/deployments)
- [Laravel Deployment Docs](https://laravel.com/docs/12.x/deployment)
- [Spotify Web API](https://developer.spotify.com/documentation/web-api)
- [YouTube Data API](https://developers.google.com/youtube/v3)

---

**Deployment Time**: ~60 seconds after first deploy 🚀

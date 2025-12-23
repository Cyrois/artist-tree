# Security Considerations: Laravel Cloud + Claude Code CLI

## Overview

This document outlines security best practices when using Laravel Cloud for deployment alongside Claude Code CLI for development. The Artist-Tree application handles sensitive API credentials from Spotify and YouTube, making proper secret management critical.

---

## 1. Environment Variables & API Keys

### Risk
Claude Code can read your local `.env` file, which contains sensitive credentials.

### Mitigation
- ✅ **Never commit `.env` to Git** (Laravel's `.gitignore` already excludes it)
- ✅ **Use Laravel Cloud's environment variable manager** for production secrets
- ✅ **Keep local `.env` separate from production**
- ✅ **Verify `.env` is in `.gitignore`**:
  ```bash
  grep -E "^\.env$" .gitignore
  ```

### Artist-Tree Sensitive Keys
- `SPOTIFY_CLIENT_ID` / `SPOTIFY_CLIENT_SECRET`
- `YOUTUBE_API_KEY`
- `DB_PASSWORD`
- `APP_KEY`

---

## 2. Code Repository Access

### Risk
If Claude Code creates commits, it might accidentally include secrets.

### Mitigation
- ✅ **Always review commits before pushing** (Claude Code shows you what it's committing)
- ✅ **Use `.env.example` with placeholder values** (commit this, not `.env`)
- ✅ **Add sensitive config files to `.gitignore`**:
  ```gitignore
  .env
  .env.backup
  .env.production
  /config/secrets/
  ```

---

## 3. Laravel Cloud Deployment

### Risk
Environment variables stored in Laravel Cloud need proper access control.

### Mitigation
- ✅ **Set environment variables directly in Laravel Cloud dashboard** (not in code)
- ✅ **Use Laravel Cloud's team permissions** to restrict who can view/edit env vars
- ✅ **Rotate API keys periodically** (especially after team member changes)

---

## 4. Database Credentials

### Risk
Claude Code can see database queries and potentially access local DB.

### Mitigation
- ✅ **Use different databases for local/production**
- ✅ **Never hardcode DB credentials** (always use `.env`)
- ✅ **Laravel Cloud manages production DB credentials** automatically

---

## 5. Session & Cache Drivers

### Risk
Local cache might persist sensitive data that Claude Code could read.

### Mitigation
- ✅ **Use Redis/database for sessions in production** (not file-based)
- ✅ **Clear local cache regularly**: `php artisan cache:clear`
- ✅ **Don't cache sensitive data without encryption**

---

## 6. Git History

### Risk
Accidentally committed secrets remain in Git history even after deletion.

### Mitigation
- ✅ **Scan commits before pushing**: `git diff --cached`
- ✅ **If you commit a secret, rotate it immediately**
- ✅ **Use tools like `git-secrets` or `gitleaks`** to prevent secret commits

---

## Recommended Workflow

```bash
# 1. Verify .env is ignored
cat .gitignore | grep "\.env"

# 2. Create .env.example with placeholders
cp .env .env.example
# Edit .env.example to replace real values with placeholders
# Example: SPOTIFY_CLIENT_SECRET=your_spotify_secret_here

# 3. Commit .env.example (safe to share)
git add .env.example
git commit -m "Add environment variable template"

# 4. Deploy to Laravel Cloud
# Set real secrets in Laravel Cloud dashboard under "Environment"
```

---

## Laravel Cloud Environment Setup

When deploying, set these in **Laravel Cloud Dashboard → Environment**:

```bash
APP_ENV=production
APP_DEBUG=false
SPOTIFY_CLIENT_ID=your_real_id
SPOTIFY_CLIENT_SECRET=your_real_secret
YOUTUBE_API_KEY=your_real_key
```

---

## Quick Security Checklist

Before deploying to production:

- [ ] `.env` is in `.gitignore`
- [ ] `.env.example` exists with placeholder values
- [ ] No API keys in code/config files (use `env()` helper)
- [ ] Production secrets configured in Laravel Cloud dashboard
- [ ] `APP_DEBUG=false` in production
- [ ] Different database credentials for local vs production
- [ ] Review all commits before pushing

---

## Claude Code Capabilities

### Claude Code CAN:
- Read local files (including `.env` if it exists)
- Create/modify code files
- Run terminal commands
- Access local database

### Claude Code CANNOT:
- Access your Laravel Cloud dashboard
- Push code without your permission
- Access production environment variables (they're in Laravel Cloud)
- Commit files without showing you first

---

## Summary

**Bottom line:** As long as `.env` isn't committed to Git and you set production secrets in Laravel Cloud's dashboard (not in code), you're secure. Always review what Claude Code commits before pushing.

---

## Additional Resources

- [Laravel Security Best Practices](https://laravel.com/docs/12.x/security)
- [Laravel Cloud Documentation](https://cloud.laravel.com/docs)
- [Git Secrets Tool](https://github.com/awslabs/git-secrets)
- [Gitleaks Scanner](https://github.com/gitleaks/gitleaks)

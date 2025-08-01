# Deployment Guide for Supreme Court Data Visualization

This guide covers deploying the application to `technosailor.com` using GitHub Actions.

## Current Server Analysis

Based on DNS/HTTP analysis of `technosailor.com`:
- **Server**: nginx on WP Engine hosting
- **Current site**: WordPress (x-powered-by: WP Engine)
- **SSL**: Enabled with proper certificates
- **Redirects**: http → https, naked domain → www

## Deployment Options

### Option 1: Subdomain Deployment (Recommended)
Deploy to a subdomain like `supremecourt.technosailor.com` or `data.technosailor.com`

**Pros:**
- Doesn't interfere with existing WordPress site
- Clean separation of applications
- Easier to manage and maintain

**Setup Required:**
1. Create subdomain DNS record
2. Configure server to serve Laravel from subdomain path
3. Set up SSL certificate for subdomain

### Option 2: Path-based Deployment
Deploy to a path like `technosailor.com/supremecourt`

**Pros:**
- Uses existing domain
- No additional DNS setup

**Cons:**
- May conflict with WordPress routing
- More complex nginx configuration needed

### Option 3: Separate Hosting Platform
Deploy to a Laravel-optimized platform and use a subdomain redirect

**Recommended platforms:**
- Railway.app
- DigitalOcean App Platform
- Vercel (with PHP support)

## GitHub Actions Setup

### Required Repository Secrets

Add these secrets to your GitHub repository settings:

```
# Server Access (for SSH deployment)
SERVER_HOST=your-server-ip-or-hostname
SERVER_USER=your-ssh-username
SERVER_SSH_KEY=your-private-ssh-key
SERVER_PORT=22

# Deployment Paths
DEPLOY_PATH=/path/to/your/app
SUBDOMAIN_PATH=/path/to/subdomain/root

# Database Configuration
DB_HOST=localhost
DB_DATABASE=supremecourt_db
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Application Configuration
APP_KEY=your-laravel-app-key
APP_ENV=production
APP_DEBUG=false
APP_URL=https://supremecourt.technosailor.com

# Redis Configuration (if available)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# OpenAI API (for LLM features)
OPENAI_API_KEY=your-openai-api-key

# Ollama Configuration (if using local LLM)
OLLAMA_BASE_URL=http://localhost:11434
```

### Repository Variables

Add these variables to your GitHub repository:

```
DEPLOY_TO_SUBDOMAIN=true
SUBDOMAIN_NAME=supremecourt
```

## Server Configuration

### For WP Engine / Shared Hosting

Since your domain is on WP Engine (WordPress hosting), you have a few options:

#### Option A: Contact WP Engine Support
Request support for:
1. PHP 8.2+ support
2. Composer access
3. Custom subdomain setup
4. Database access for Laravel

#### Option B: Hybrid Approach
1. Keep WordPress on main domain
2. Use external hosting (Railway/DigitalOcean) for Laravel app
3. Set up subdomain CNAME to point to external hosting
4. Update GitHub Actions to deploy to external platform

### Server Requirements

For the Laravel application to run properly, you need:

```bash
# PHP Requirements
PHP >= 8.2
BCMath PHP Extension
Ctype PHP Extension
Fileinfo PHP Extension
JSON PHP Extension
Mbstring PHP Extension
OpenSSL PHP Extension
PDO PHP Extension
Tokenizer PHP Extension
XML PHP Extension
Redis PHP Extension (optional)

# Database
MySQL 8.0+ or SQLite
Redis (optional, for caching)

# Web Server
nginx or Apache with mod_rewrite
```

## Deployment Commands

### Manual Deployment Steps

If deploying manually via SSH:

```bash
# 1. Clone repository
git clone https://github.com/yourusername/historical-data-viz.git
cd historical-data-viz

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Environment setup
cp .env.example .env
php artisan key:generate
# Edit .env with your database and other settings

# 4. Database setup
php artisan migrate --force

# 5. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# 6. Set permissions
chmod -R 775 storage bootstrap/cache
```

### Nginx Configuration Example

If you have access to nginx configuration:

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name supremecourt.technosailor.com;
    
    root /path/to/your/app/public;
    index index.php index.html;
    
    # SSL configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

## Alternative: Railway Deployment

If WP Engine doesn't support Laravel well, I recommend Railway:

### Railway Deployment Workflow

```yaml
# .github/workflows/railway-deploy.yml
name: Deploy to Railway

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
      with:
        node-version: '18'
    - run: |
        npm i -g @railway/cli
        railway login --token ${{ secrets.RAILWAY_TOKEN }}
        railway up --service ${{ secrets.RAILWAY_SERVICE }}
```

Then set up a CNAME record:
```
supremecourt.technosailor.com → your-app.up.railway.app
```

## Next Steps

1. **Choose deployment approach** (subdomain vs external hosting)
2. **Set up GitHub repository secrets**
3. **Configure server/hosting environment**
4. **Test deployment workflow**
5. **Set up SSL certificates**
6. **Configure domain/subdomain DNS**

Let me know which approach you'd prefer, and I can help you set up the specific configuration!
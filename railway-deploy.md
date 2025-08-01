# 🚂 Railway Deployment Guide for Supreme Court Data Visualization

## 🚀 Quick Deploy to Railway

### Option 1: Deploy Button (Recommended)
Click this button to deploy instantly:

[![Deploy on Railway](https://railway.app/button.svg)](https://railway.app/template/your-template-id)

### Option 2: Manual Railway CLI Deployment

#### Prerequisites
1. Install Railway CLI: `npm install -g @railway/cli`
2. Login to Railway: `railway login`

#### Deployment Steps

```bash
# 1. Navigate to your project
cd /path/to/historical-data-viz

# 2. Initialize Railway project
railway login
railway init

# 3. Add required services
railway add postgresql
railway add redis

# 4. Set environment variables
railway variables set APP_NAME="Supreme Court Data Visualization"
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false

# Generate and set APP_KEY
php artisan key:generate --show
railway variables set APP_KEY="your-generated-key"

# Optional: Add API keys for full functionality
railway variables set JUSTIA_API_KEY="your-justia-key"
railway variables set OPENAI_API_KEY="your-openai-key"

# 5. Deploy
railway up
```

## 📋 Required Railway Services

Your app needs these Railway services:

### 1. Web Service (Automatic)
- **Type**: Web service
- **Build**: Nixpacks (automatic Laravel detection)
- **Port**: Auto-detected from Laravel

### 2. PostgreSQL Database
```bash
railway add postgresql
```
- Railway automatically sets: `PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD`
- These map to Laravel's `DB_*` variables in `.env.railway`

### 3. Redis Cache & Sessions
```bash
railway add redis
```
- Railway automatically sets: `REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_URL`
- Used for caching, sessions, and queue storage

## 🔧 Environment Configuration

Railway will automatically use these environment variables:

### Core Laravel Settings
- `APP_URL`: Automatically set to your Railway app URL
- `DB_*`: Automatically configured from PostgreSQL service
- `REDIS_*`: Automatically configured from Redis service

### Supreme Court App Settings
- `JUSTIA_API_KEY`: Set manually for case data enrichment
- `OPENAI_API_KEY`: Set manually for AI analysis features
- `ENABLE_LLM_ANALYSIS`: Set to `false` (Ollama not available in cloud)

## 🛡️ Production Optimizations

The Railway deployment includes:

- ✅ **PostgreSQL Database**: Scalable cloud database
- ✅ **Redis Caching**: High-performance caching and sessions
- ✅ **Optimized Build**: Composer production install, asset compilation
- ✅ **Laravel Optimizations**: Config, route, and view caching
- ✅ **Queue Workers**: Background job processing
- ✅ **Error Handling**: Production-safe error pages
- ✅ **Security**: HTTPS enforced, secure session handling

## 📊 Available Features on Railway

### ✅ Fully Supported
- **Interactive Dashboard**: All D3.js visualizations work
- **Supreme Court Case Search**: Full-text search and filtering
- **Precedential Analysis**: Case citation networks and importance scoring
- **Justice Language Analysis**: Writing pattern analysis
- **Topic Trend Analysis**: Legal topic evolution over time
- **Interactive Heatmaps**: All three heatmap visualizations
- **Filament Admin Panel**: Complete data management interface
- **API Endpoints**: All REST APIs for data retrieval

### ⚠️ Cloud Limitations
- **Local LLM (Ollama)**: Not available in cloud deployment
- **OpenAI Integration**: Requires API key for AI chat features
- **Large Dataset Processing**: Memory limits may require optimization

## 🔗 Post-Deployment Setup

After successful deployment:

1. **Run Migrations**:
   ```bash
   railway run php artisan migrate --force
   ```

2. **Create Admin User**:
   ```bash
   railway run php artisan make:filament-user
   ```

3. **Import Sample Data** (if needed):
   ```bash
   railway run php artisan supreme-court:import-data --limit=100
   ```

4. **Access Your App**:
   - Main App: `https://your-app-name.railway.app`
   - Admin Panel: `https://your-app-name.railway.app/admin`

## 💰 Railway Pricing

- **Starter Plan**: $5/month
- **Pro Plan**: $20/month  
- **Hobby Plan**: Pay per usage

Your Supreme Court app should run comfortably on the Starter plan.

## 🔧 Railway CLI Commands

```bash
# View logs
railway logs

# Run artisan commands
railway run php artisan migrate
railway run php artisan queue:work

# Access shell
railway shell

# View environment variables
railway variables

# Scale services
railway scale web 2
```

## 🚨 Troubleshooting

### Common Issues:

1. **Database Connection Errors**:
   - Ensure PostgreSQL service is added and connected
   - Check that `DB_CONNECTION=pgsql` in environment

2. **Redis Connection Errors**:
   - Ensure Redis service is added and connected
   - Verify `CACHE_STORE=redis` and `SESSION_DRIVER=redis`

3. **Asset Loading Issues**:
   - Run `railway run php artisan storage:link`
   - Ensure `APP_URL` is set correctly

4. **Memory Errors**:
   - Reduce `ANALYSIS_BATCH_SIZE` to 25 or lower
   - Consider upgrading Railway plan for more memory

## 🎉 Success!

Once deployed, your Supreme Court Data Visualization app will be live at:
`https://your-app-name.railway.app`

Happy analyzing! 🏛️⚖️📊
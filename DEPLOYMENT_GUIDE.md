# Free Deployment Guide

This application is configured for **Railway** deployment and ready to deploy for free.

## 🚂 Railway Deployment (Recommended)

### Prerequisites
- GitHub repository
- Railway account (free at [railway.app](https://railway.app))
- LaunchDarkly account (free tier available)

### Steps

1. **Push code to GitHub** (if not already done)
   ```bash
   git add .
   git commit -m "Ready for Railway deployment"
   git push origin main
   ```

2. **Deploy to Railway**
   - Go to [railway.app](https://railway.app) and sign up
   - Click "New Project" → "Deploy from GitHub repo"
   - Select your repository
   - Railway will automatically detect Laravel and use your `railway.json` config

3. **Set Environment Variables**
   In Railway dashboard, go to Variables tab and add:
   ```
   APP_NAME=SCOTUS
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app.railway.app
   
   # Database (Railway provides PostgreSQL for free)
   DB_CONNECTION=pgsql
   
   
   # OpenAI (optional, for AI analysis)
   OPENAI_API_KEY=your-openai-key
   
   # Session & Cache
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```

4. **Add Database**
   - In Railway dashboard, click "New" → "Database" → "PostgreSQL"
   - Railway will automatically set database environment variables

5. **Deploy**
   - Railway deploys automatically when you push to GitHub
   - Monitor deployment in Railway dashboard

## 🔧 Your Project is Already Configured With:

- ✅ `railway.json` - Railway V2 configuration
- ✅ `start.sh` - Production startup script
- ✅ `Procfile` - Web and worker processes  
- ✅ SQLite database (works out of the box)
- ✅ Filament admin panel
- ✅ All dependencies in `composer.json`

## 🎯 Alternative Free Options

### Fly.io
1. Install flyctl CLI
2. Run `fly launch` in project directory
3. Configure environment variables
4. Deploy with `fly deploy`

### Render
1. Connect GitHub repository
2. Select "Web Service"
3. Configure build and start commands
4. Add environment variables

## 📝 Post-Deployment Steps

1. **Access admin panel**: `https://your-app.railway.app/admin`
2. **Create admin user**:
   ```bash
   # Via Railway CLI or dashboard console
   php artisan make:filament-user
   ```
3. **Test application features**
4. **Import historical data** (if needed)

## 🚨 Important Notes

- Railway free tier includes 500 hours/month
- SQLite database works for development/small projects
- For production, consider PostgreSQL (free on Railway)
- Monitor resource usage in Railway dashboard

Your application is deployment-ready! 🚀
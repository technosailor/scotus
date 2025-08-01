#!/bin/bash

echo "🚂 Setting up Supreme Court Data Visualization for Railway..."

# Install Railway CLI if not present
if ! command -v railway &> /dev/null; then
    echo "Installing Railway CLI..."
    npm install -g @railway/cli
fi

# Login to Railway
echo "Please login to Railway:"
railway login

# Initialize Railway project
echo "Initializing Railway project..."
railway init

# Add required services
echo "Adding PostgreSQL database..."
railway add postgresql

echo "Adding Redis cache..."
railway add redis

# Generate Laravel application key
echo "Generating Laravel application key..."
APP_KEY=$(php artisan key:generate --show)

# Set core environment variables
echo "Setting environment variables..."
railway variables set APP_NAME="Supreme Court Data Visualization"
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false
railway variables set APP_KEY="$APP_KEY"
railway variables set DB_CONNECTION=pgsql
railway variables set CACHE_STORE=redis
railway variables set SESSION_DRIVER=redis
railway variables set QUEUE_CONNECTION=redis

# Set Supreme Court specific variables
railway variables set ENABLE_LLM_ANALYSIS=false
railway variables set ENABLE_PRECEDENTIAL_ANALYSIS=true
railway variables set ANALYSIS_BATCH_SIZE=50
railway variables set REDIS_OPINION_PREFIX="supreme_court:opinions:"

echo "🎯 Optional: Set API keys for enhanced functionality"
echo "Run these commands to add API keys:"
echo "  railway variables set JUSTIA_API_KEY=\"your-justia-key\""
echo "  railway variables set OPENAI_API_KEY=\"your-openai-key\""

echo ""
echo "🚀 Ready to deploy! Run:"
echo "  railway up"
echo ""
echo "After deployment, run migrations:"
echo "  railway run php artisan migrate --force"
echo "  railway run php artisan make:filament-user"

echo "✅ Railway setup complete!"
#!/bin/bash

# Railway startup script for Laravel
echo "🚂 Starting SCOTUS on Railway..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null || sleep 10

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache config
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link

# Start the application
echo "🌟 Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
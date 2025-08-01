#!/bin/bash

echo "🚂 Post-deployment setup for Railway..."

# Run database migrations
echo "Running database migrations..."
railway run php artisan migrate --force

# Clear and cache config for production
echo "Optimizing Laravel for production..."
railway run php artisan config:cache
railway run php artisan route:cache  
railway run php artisan view:cache

# Create storage symlink
echo "Creating storage symlink..."
railway run php artisan storage:link

# Import sample data (optional)
echo "🎯 Optional: Import sample Supreme Court data"
echo "Run: railway run php artisan supreme-court:import-data --limit=100"

# Create admin user (interactive)
echo "🔐 Create admin user for Filament panel:"
echo "Run: railway run php artisan make:filament-user"

echo "✅ Post-deployment setup complete!"
echo ""
echo "🌐 Your app should be live at:"
echo "https://your-app-name.railway.app"
echo ""
echo "⚙️ Admin panel available at:"
echo "https://your-app-name.railway.app/admin"
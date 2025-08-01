#!/bin/bash

echo "🚀 Starting Supreme Court Data Visualization Application..."

# Switch to root for system operations
sudo -i <<'EOF'

# Wait for database file to be ready
echo "🗄️ Checking database..."
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chown www:www /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
fi

# Run database migrations
echo "🔄 Running database migrations..."
cd /var/www/html
sudo -u www php artisan migrate --force

# Clear and optimize caches
echo "⚡ Optimizing application..."
sudo -u www php artisan config:cache
sudo -u www php artisan route:cache
sudo -u www php artisan view:cache

# Download Ollama model in background
echo "🤖 Starting Ollama model download (background)..."
{
    sleep 30
    sudo -u www ollama pull llama2:7b-chat
} &

# Start all services via supervisor
echo "🌟 Starting all services..."
/usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

EOF

echo "✅ Supreme Court Data Visualization is running!"
echo "🌐 Access points:"
echo "  📊 Main App: http://localhost:80"
echo "  📊 Alt Port: http://localhost:8000"
echo "  ⚙️  Admin Panel: http://localhost:80/admin"
echo "  🤖 Ollama API: http://localhost:11434"
echo "  💻 PHP-FPM: http://localhost:9000"
echo ""
echo "🎉 Ready for Supreme Court case analysis!"
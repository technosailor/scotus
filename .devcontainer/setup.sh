#!/bin/bash

echo "🚀 Setting up Supreme Court Data Visualization - Full Stack Environment..."

# Update system packages
sudo apt-get update && sudo apt-get upgrade -y

# Install system dependencies
echo "📦 Installing system dependencies..."
sudo apt-get install -y \
    curl \
    wget \
    unzip \
    git \
    sqlite3 \
    redis-server \
    nginx \
    supervisor \
    htop \
    tree \
    jq \
    build-essential

# Install all required PHP extensions for our Laravel app
echo "🐘 Installing PHP extensions..."
sudo apt-get install -y \
    php8.4-cli \
    php8.4-fpm \
    php8.4-sqlite3 \
    php8.4-mysql \
    php8.4-redis \
    php8.4-gd \
    php8.4-curl \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-soap \
    php8.4-xsl \
    php8.4-fileinfo \
    php8.4-tokenizer \
    php8.4-ctype \
    php8.4-json \
    php8.4-pdo \
    php8.4-dom

# Verify PHP configuration
php -v
php -m | grep -E "(pdo|sqlite|redis|gd|curl|mbstring|xml|zip|bcmath|intl)"

# Install Composer globally if not present
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Verify Composer
composer --version

# Install Laravel dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# Install Node dependencies  
echo "📦 Installing Node.js dependencies..."
npm install

# Verify Node/npm versions
node --version
npm --version

# Copy environment file and customize for Codespaces
echo "⚙️ Setting up environment configuration..."
cp .env.example .env

# Update .env for Codespaces environment
cat >> .env << 'EOF'

# Codespaces/GitHub specific configuration
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database - SQLite for development
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Cache and Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

# Redis configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Ollama Local LLM
OLLAMA_BASE_URL=http://localhost:11434

# File permissions
FILESYSTEM_DISK=local

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=debug

# GitHub Codespaces optimizations
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,*.github.dev,*.githubpreview.dev
EOF

# Generate application key
php artisan key:generate --force

# Create all necessary directories
echo "📁 Creating application directories..."
mkdir -p database storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# Create SQLite database
echo "🗄️ Setting up SQLite database..."
touch database/database.sqlite
chmod 664 database/database.sqlite

# Set up Redis
echo "🔴 Setting up Redis server..."
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Test Redis connection
redis-cli ping || echo "Redis setup will complete in background"

# Run database migrations and seeders
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Create storage link
php artisan storage:link

# Install and setup Ollama for local LLM
echo "🤖 Setting up Ollama for local LLM..."
curl -fsSL https://ollama.ai/install.sh | sh

# Create systemd service for Ollama in Codespaces
sudo tee /etc/systemd/system/ollama.service > /dev/null << 'EOF'
[Unit]
Description=Ollama Service
After=network-online.target

[Service]
ExecStart=/usr/local/bin/ollama serve
User=vscode
Group=vscode
Restart=always
RestartSec=3
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="OLLAMA_HOST=0.0.0.0:11434"

[Install]
WantedBy=default.target
EOF

# Start Ollama service
sudo systemctl daemon-reload
sudo systemctl enable ollama
sudo systemctl start ollama

# Wait for Ollama to start
sleep 10

# Download AI models for our application
echo "🧠 Downloading AI models (this may take a while)..."
ollama pull llama2:7b-chat &
OLLAMA_PID=$!

# Build frontend assets
echo "🎨 Building frontend assets..."
npm run build

# Also start Vite dev server in background for development
npm run dev &

# Set up nginx configuration for production-like testing
echo "🌐 Setting up nginx configuration..."
sudo tee /etc/nginx/sites-available/supreme-court > /dev/null << 'EOF'
server {
    listen 80;
    server_name localhost;
    root /workspaces/historical-data-viz/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Handle Laravel API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle Filament admin routes
    location /admin {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/supreme-court /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Start PHP-FPM and nginx
sudo systemctl enable php8.4-fpm
sudo systemctl start php8.4-fpm
sudo systemctl enable nginx
sudo systemctl restart nginx

# Create supervisor configuration for Laravel queue worker
echo "⚡ Setting up Laravel queue worker..."
sudo tee /etc/supervisor/conf.d/laravel-worker.conf > /dev/null << 'EOF'
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /workspaces/historical-data-viz/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=vscode
numprocs=2
redirect_stderr=true
stdout_logfile=/workspaces/historical-data-viz/storage/logs/worker.log
stopwaitsecs=3600
EOF

# Start supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Set proper permissions
echo "🔐 Setting file permissions..."
sudo chown -R vscode:vscode /workspaces/historical-data-viz
chmod -R 755 /workspaces/historical-data-viz
chmod -R 775 storage bootstrap/cache database
chmod 664 database/database.sqlite

# Optimize Laravel for development
echo "⚡ Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create sample data if needed
echo "📊 Setting up sample data..."
php artisan db:seed --force || echo "Seeding completed or not needed"

# Test all services
echo "🧪 Testing services..."
echo "✓ PHP Version: $(php -v | head -n1)"
echo "✓ Composer Version: $(composer --version)"
echo "✓ Node Version: $(node --version)"
echo "✓ Redis Status: $(redis-cli ping 2>/dev/null || echo 'Not ready yet')"
echo "✓ Nginx Status: $(sudo systemctl is-active nginx)"
echo "✓ PHP-FPM Status: $(sudo systemctl is-active php8.4-fpm)"
echo "✓ Ollama Status: $(sudo systemctl is-active ollama)"

# Create helpful aliases
echo "🔧 Creating helpful aliases..."
cat >> ~/.zshrc << 'EOF'

# Supreme Court App aliases
alias serve='php artisan serve --host=0.0.0.0 --port=8000'
alias tinker='php artisan tinker'
alias migrate='php artisan migrate'
alias fresh='php artisan migrate:fresh --seed'
alias dev='composer dev'
alias build='npm run build'
alias watch='npm run dev'

# Laravel shortcuts
alias pa='php artisan'
alias pam='php artisan migrate'
alias par='php artisan route:list'
alias pac='php artisan config:cache'
alias paq='php artisan queue:work'

# Git shortcuts
alias gs='git status'
alias ga='git add'
alias gc='git commit'
alias gp='git push'

# System shortcuts
alias ll='ls -la'
alias la='ls -la'
alias ..='cd ..'
alias ...='cd ../..'

echo "🎯 Supreme Court Data Visualization - Ready!"
echo "📊 Dashboard: http://localhost:8000"
echo "⚙️  Admin Panel: http://localhost:8000/admin"
echo "🤖 Ollama API: http://localhost:11434"
EOF

# Wait for Ollama model download to complete
wait $OLLAMA_PID 2>/dev/null || echo "Ollama model download continuing in background"

echo "✅ Full development environment setup complete!"
echo ""
echo "🎯 Quick start commands:"
echo "  serve            - Start Laravel development server"
echo "  composer dev     - Start full development stack (Laravel + Vite + Queue + Logs)"
echo "  pa serve         - Start Laravel server"
echo "  npm run dev      - Start Vite dev server"
echo "  pa tinker        - Laravel REPL"
echo "  pa migrate       - Run database migrations"
echo "  pa queue:work    - Start queue worker"
echo ""
echo "🌐 Access points:"
echo "  📊 Main App: http://localhost:8000"
echo "  ⚙️  Admin Panel: http://localhost:8000/admin (run 'pa make:filament-user' to create admin)"
echo "  🤖 Ollama API: http://localhost:11434"
echo "  🔴 Redis: redis://localhost:6379"
echo ""
echo "📁 Important files:"
echo "  🔧 Config: .env"
echo "  🗄️  Database: database/database.sqlite"
echo "  📝 Logs: storage/logs/"
echo "  🎨 Assets: public/"
echo ""
echo "🎉 Happy coding! Your Supreme Court Data Visualization app is ready!"

# Create a startup script
cat > ~/start-app.sh << 'EOF'
#!/bin/bash
echo "🚀 Starting Supreme Court Data Visualization..."

# Start services
sudo systemctl start redis-server
sudo systemctl start nginx
sudo systemctl start php8.4-fpm
sudo systemctl start ollama
sudo supervisorctl start all

# Start development servers
cd /workspaces/historical-data-viz
php artisan serve --host=0.0.0.0 --port=8000 &
npm run dev &

echo "✅ All services started!"
echo "📊 Dashboard: http://localhost:8000"
echo "⚙️  Admin: http://localhost:8000/admin"
EOF

chmod +x ~/start-app.sh

echo ""
echo "💡 Pro tip: Run '~/start-app.sh' to start all services at once!"
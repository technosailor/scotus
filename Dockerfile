# Supreme Court Data Visualization - Full Stack Production Docker
FROM php:8.4-fpm-bullseye

# Set labels for the image
LABEL maintainer="Supreme Court Data Visualization Team"
LABEL description="Laravel 12 application with Supreme Court case analysis and visualization"
LABEL version="1.0"

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # Essential packages
    git \
    curl \
    wget \
    unzip \
    zip \
    # PHP extension dependencies
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    # Database drivers
    sqlite3 \
    libsqlite3-dev \
    default-mysql-client \
    # Redis
    redis-tools \
    # Web server and process management
    nginx \
    supervisor \
    # Node.js for asset building
    nodejs \
    npm \
    # System utilities
    htop \
    tree \
    jq \
    # Build tools
    build-essential \
    # Image optimization
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        xml \
        dom \
        fileinfo \
        tokenizer \
        ctype

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Ollama for local LLM support
RUN curl -fsSL https://ollama.ai/install.sh | sh

# Create application user
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

# Copy application files
COPY --chown=www:www . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm ci && npm run build && npm cache clean --force

# Create necessary directories
RUN mkdir -p storage/app/public \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
             database \
    && touch database/database.sqlite

# Set proper permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 storage bootstrap/cache database \
    && chmod 664 database/database.sqlite

# Copy configuration files
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Remove default nginx site and enable our app
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/

# Copy environment file and configure for production
RUN cp .env.example .env

# Set up Laravel
RUN php artisan key:generate --force \
    && php artisan storage:link \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Create startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Switch to www user
USER www

# Expose ports
EXPOSE 80 8000 9000 11434

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/health || exit 1

# Start application
CMD ["/usr/local/bin/start.sh"]
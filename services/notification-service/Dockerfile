# PHP 8.2 with required extensions for Lumen notification service
FROM php:8.2-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql-dev \
    redis \
    supervisor \
    nginx \
    && docker-php-ext-install pdo pdo_pgsql zip opcache pcntl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Install PHP Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Create necessary directories and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Create basic nginx config if docker files don't exist
RUN echo "user www-data;" > /etc/nginx/nginx.conf

# Create basic supervisor config
RUN echo "[supervisord]" > /etc/supervisor/conf.d/supervisor.conf

# Create a simple startup script if none exists
RUN echo -e '#!/bin/sh\nphp-fpm -D\nexec php artisan serve --host=0.0.0.0 --port=8004' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Expose port
EXPOSE 8004

# Start services
CMD ["/usr/local/bin/start.sh"]
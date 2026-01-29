# Multi-stage Dockerfile for CodeIgniter 4 API

# Stage 1: Composer dependencies
FROM composer:2 AS composer-build

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

# Stage 2: Production image
FROM php:8.2-apache

LABEL maintainer="CodeIgniter 4 API Starter"
LABEL description="Production-ready CI4 API with JWT authentication"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    intl \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    gd \
    opcache

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy custom PHP configuration
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files from composer stage
COPY --from=composer-build /app /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/writable

# Create necessary directories
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads \
    && chown -R www-data:www-data writable

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port 80
EXPOSE 80

# Switch to www-data user
USER www-data

# Start Apache
CMD ["apache2-foreground"]

# Production-ready Dockerfile for Railway deployment.
# Based on php:8.2-cli-alpine for a small image size with just enough
# extensions to run Laravel + our features.

FROM php:8.2-cli-alpine AS base

# System packages + PHP extensions
RUN apk add --no-cache \
        bash \
        git \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        curl \
        zip \
        unzip \
        icu-dev \
        imagemagick-dev \
        imagemagick \
        nodejs \
        npm \
        $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del $PHPIZE_DEPS

# Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (cache layer)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# Install JS deps + build frontend
COPY package.json package-lock.json vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/ ./resources/
RUN npm ci && npm run build

# Copy application
COPY . .

# Finish composer install (now has all source for autoload)
RUN composer dump-autoload --optimize --no-interaction

# Permissions
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Railway injects $PORT. Default to 8080 for local testing.
ENV PORT=8080
EXPOSE 8080

# Custom entrypoint: run migrations, seed if needed, then start server
COPY railway/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["/usr/local/bin/entrypoint.sh"]

FROM php:8.3-fpm-alpine
# Build version: 2026-07-04-v3

# Instalar dependencias del sistema compatibles con Alpine
RUN apk update && apk add --no-cache \
    git \
    curl \
    openssl \
    postgresql-dev \
    nodejs \
    npm \
    make \
    g++ \
    libpng-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    tzdata \
    && docker-php-ext-configure gd \
        --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        opcache \
        pcntl \
        exif \
        gd \
        zip \
        intl \
        bcmath \
        mbstring \
    && cp /usr/share/zoneinfo/America/Argentina/Buenos_Aires /etc/localtime \
    && echo "America/Argentina/Buenos_Aires" > /etc/timezone \
    && rm -rf /var/cache/apk/*

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin --filename=composer

# Directorio de trabajo
WORKDIR /var/www

# Copiar proyecto
COPY . .

# Crear directorios necesarios antes de composer
RUN mkdir -p /var/www/bootstrap/cache \
    && mkdir -p /var/www/storage/framework/sessions \
    && mkdir -p /var/www/storage/framework/views \
    && mkdir -p /var/www/storage/framework/cache \
    && mkdir -p /var/www/storage/logs \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage

# Instalar dependencias PHP sin dev
RUN composer install --prefer-dist --no-dev --optimize-autoloader

# Instalar dependencias JS y compilar
RUN npm install && npm run build

# Copiar .env base
RUN cp .env.example .env

# Generar clave
RUN php artisan key:generate

# Permisos finales
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

EXPOSE 8000

RUN printf '#!/bin/sh\n\
set -e\n\
\n\
echo "=== GymControl - Iniciando en Render ==="\n\
\n\
php artisan optimize:clear\n\
\n\
php artisan storage:link || true\n\
\n\
php artisan vendor:publish --tag=livewire:config --force\n\
php artisan vendor:publish --tag=laravel-pagination --force\n\
\n\
php artisan migrate --force\n\
php artisan db:seed --class=DatabaseSeeder --force 2>/dev/null || true\n\
php artisan tokens:normalizar\n\
\n\
php artisan optimize\n\
\n\
echo "=== Deploy completado. Iniciando servidor ==="\n\
export TZ=America/Argentina/Buenos_Aires\n\
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}\n\
' > /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container

CMD ["/usr/local/bin/start-container"]

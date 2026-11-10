FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    curl \
    curl-dev \
    freetype-dev \
    g++ \
    git \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    make \
    nodejs \
    npm \
    oniguruma-dev \
    postgresql-dev \
    tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
    && cp /usr/share/zoneinfo/America/Argentina/Buenos_Aires /etc/localtime \
    && echo "America/Argentina/Buenos_Aires" > /etc/timezone

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www
COPY --chown=www-data:www-data . .

ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=https
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R 775 bootstrap/cache storage

COPY docker/start-container.sh /usr/local/bin/start-container
RUN chmod 755 /usr/local/bin/start-container

ENV SERVICE_ROLE=web
EXPOSE 8000 8080
USER www-data

ENTRYPOINT ["/usr/local/bin/start-container"]

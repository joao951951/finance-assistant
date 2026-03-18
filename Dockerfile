# ─── Stage 1: Build (PHP + Node juntos para o Wayfinder funcionar) ───────────
FROM php:8.3-cli-alpine AS builder

# Node.js + dependências de sistema
RUN apk add --no-cache \
    nodejs \
    npm \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    unzip

# Extensões PHP mínimas (necessárias para artisan rodar)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql zip gd bcmath mbstring

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instala dependências PHP (produção)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Prepara .env mínimo para o artisan rodar sem banco
RUN cp .env.example .env \
    && sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=/' .env \
    && php artisan key:generate --no-interaction

# Instala dependências Node + build (Wayfinder chama php artisan internamente)
RUN npm ci && npm run build

# ─── Stage 2: Runtime (PHP-FPM + Nginx) ──────────────────────────────────────
FROM php:8.3-fpm-alpine

# Dependências de sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    curl

# Extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
        opcache \
        pcntl \
        mbstring

WORKDIR /var/www/html

# Copia app + assets compilados + vendor do stage builder
COPY --from=builder /app .

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configs do Docker
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

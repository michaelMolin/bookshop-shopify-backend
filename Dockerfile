FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Installa dipendenze di sistema e dev tools
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    mysql-client \
    libpq-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    libxml2-dev \
    make

# Installa estensioni PHP necessarie per Laravel
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    bcmath \
    xml

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia il file composer.json e composer.lock (se esiste)
COPY composer.json composer.lock* ./

# Installa dipendenze PHP di Laravel
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copia tutto il codice dell'applicazione
COPY . .

# Crea le directory di storage e bootstrap
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache

# Imposta i permessi corretti per Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Espone la porta 9000 per PHP-FPM
EXPOSE 9000

# Comando di avvio
CMD ["php-fpm"]

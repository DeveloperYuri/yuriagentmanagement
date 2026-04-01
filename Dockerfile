FROM php:8.2-fpm

# Install dependencies sistem & tools
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    zip \
    unzip \
    git \
    curl \
    gnupg

# --- TAMBAHKAN NODE.JS DI SINI ---
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install ekstensi PHP untuk Postgres & GD
RUN docker-php-ext-install pdo pdo_pgsql gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www
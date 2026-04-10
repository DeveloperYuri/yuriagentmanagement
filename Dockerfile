FROM php:8.2-fpm

# Install dependencies sistem & tools
# RUN apt-get update && apt-get install -y \
#     libpq-dev \
#     libpng-dev \
#     zip \
#     unzip \
#     git \
#     curl \
#     gnupg
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    gnupg \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_pgsql gd zip

# --- TAMBAHKAN NODE.JS DI SINI ---
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install ekstensi PHP untuk Postgres & GD
RUN docker-php-ext-install pdo pdo_pgsql gd

# RUN apt-get update && apt-get install -y \
#     libzip-dev \
#     && docker-php-ext-install zip

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www
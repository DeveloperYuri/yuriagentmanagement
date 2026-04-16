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
    # Tambahan python3 dan pip di sini
    # python3 \
    # python3-pip \
    # python3-dev \
    python3 \
    python3-pandas \
    python3-numpy \
    python3-openpyxl \
    python3-xlsxwriter \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_pgsql gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- TAMBAHKAN NODE.JS DI SINI ---
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# --- TAMBAHKAN PANDAS & LIBRARY PYTHON ---
# --break-system-packages diperlukan di versi Python terbaru agar tidak konflik dengan apt
# --- PYTHON LIB ---
# RUN pip3 install --no-cache-dir pandas numpy xlsxwriter openpyxl --break-system-packages

# Install ekstensi PHP untuk Postgres & GD
RUN docker-php-ext-install pdo pdo_pgsql pgsql gd

# RUN apt-get update && apt-get install -y \
#     libzip-dev \
#     && docker-php-ext-install zip

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www
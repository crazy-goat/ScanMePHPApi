FROM php:8.4-cli

WORKDIR /app

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install zip pcntl \
    && apt-get clean

# Download and install ScanMePHP C extension (prebuilt binary)
RUN PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . PHP_MINOR_VERSION;") && \
    EXT_FILE="php-ext-linux-glibc-x86_64-php${PHP_VERSION}.so" && \
    EXT_URL="https://github.com/crazy-goat/ScanMePHP/releases/download/v0.4.11/${EXT_FILE}" && \
    EXT_DIR=$(php -r "echo ini_get('extension_dir');") && \
    echo "Downloading ${EXT_URL}..." && \
    curl -fsSL -o "${EXT_DIR}/scanmeqr.so" "${EXT_URL}" && \
    ls -la "${EXT_DIR}/scanmeqr.so" && \
    echo "extension=scanmeqr.so" > /usr/local/etc/php/conf.d/scanmeqr.ini && \
    php -m | grep scanmeqr || echo "Extension not loaded yet (expected before composer install)"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

EXPOSE 8787

CMD ["php", "start.php", "start"]

FROM php:8.4-cli

WORKDIR /app

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install zip pcntl \
    && apt-get clean

# Download ScanMePHP libraries
RUN PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;") && \
    EXT_FILE="php-ext-linux-glibc-x86_64-php${PHP_VERSION}.so" && \
    LIB_FILE="libscanme_qr-linux-glibc-x86_64.so" && \
    echo "PHP Version: ${PHP_VERSION}" && \
    echo "Downloading extension: ${EXT_FILE}" && \
    echo "Downloading library: ${LIB_FILE}"

# Download PHP extension
RUN PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;") && \
    curl -fsSL -o "/usr/local/lib/php/extensions/no-debug-non-zts-20240924/scanmeqr.so" \
    "https://github.com/crazy-goat/ScanMePHP/releases/download/v0.4.11/php-ext-linux-glibc-x86_64-php${PHP_VERSION}.so" && \
    echo "extension=scanmeqr.so" > /usr/local/etc/php/conf.d/scanmeqr.ini

# Download shared library and install system-wide
RUN curl -fsSL -o "/usr/lib/libscanme_qr.so" \
    "https://github.com/crazy-goat/ScanMePHP/releases/download/v0.4.11/libscanme_qr-linux-glibc-x86_64.so" && \
    ldconfig

# Verify installation
RUN php -m | grep -i scanmeqr || echo "Extension check..."

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

EXPOSE 8787

CMD ["php", "start.php", "start"]

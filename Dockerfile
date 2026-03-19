FROM php:8.4-cli

WORKDIR /app

# Install dependencies and build tools for the extension
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    build-essential \
    cmake \
    && docker-php-ext-install zip pcntl \
    && apt-get clean

# Download and build ScanMePHP C extension from release
RUN cd /tmp && \
    git clone --depth 1 --branch v0.4.11 https://github.com/crazy-goat/ScanMePHP.git && \
    cd ScanMePHP && \
    mkdir build && cd build && \
    cmake .. && \
    make && \
    make install && \
    docker-php-ext-enable scanmeqr && \
    rm -rf /tmp/ScanMePHP

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

EXPOSE 8787

CMD ["php", "start.php", "start"]

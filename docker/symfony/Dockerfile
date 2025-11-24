FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    bash \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    sudo \
    python3 \
    py3-pip

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Set working directory
WORKDIR /var/www/symfony

# Configure PHP for development
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini

# Create www-data user with proper permissions
RUN adduser -D -u 1000 www-data || true

# Configure sudo for www-data (for devcontainer)
RUN echo "www-data ALL=(ALL) NOPASSWD: ALL" > /etc/sudoers.d/www-data && \
    chmod 0440 /etc/sudoers.d/www-data

# Ensure proper ownership of working directory
RUN mkdir -p /var/www/symfony/vendor && \
    chown -R www-data:www-data /var/www/symfony

USER www-data

EXPOSE 8000

CMD ["symfony", "server:start", "--no-tls", "--port=8000"]

FROM php:8.3-cli

ENV COMPOSER_ALLOW_SUPERUSER=1

# Sistema + extensões PHP necessárias ao Laravel (PostgreSQL, Redis, filas)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git \
        unzip \
        curl \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-install pdo_pgsql bcmath pcntl zip \
    && curl -fsSL https://github.com/phpredis/phpredis/archive/refs/tags/6.0.2.tar.gz -o /tmp/redis.tar.gz \
    && tar -xzf /tmp/redis.tar.gz -C /tmp \
    && cd /tmp/phpredis-6.0.2 \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable redis \
    && cd / \
    && rm -rf /tmp/redis.tar.gz /tmp/phpredis-6.0.2 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

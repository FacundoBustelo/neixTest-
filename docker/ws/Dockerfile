
FROM php:8.2-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev zlib1g-dev     && docker-php-ext-install pdo_mysql zip sockets pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json /var/www/composer.json
RUN composer install --no-dev --prefer-dist --no-interaction || true

# Copiamos código (montado como volumen en runtime también)
COPY app /var/www/app

# Exponer puerto WS
EXPOSE 8080

CMD ["php", "app/Infrastructure/ws/server.php"]

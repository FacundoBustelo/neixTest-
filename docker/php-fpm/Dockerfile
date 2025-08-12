
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \ 
        git unzip libzip-dev zlib1g-dev     && docker-php-ext-install pdo_mysql zip sockets

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Instalar dependencias PHP (Ratchet/React)
COPY composer.json /var/www/composer.json
RUN composer install --no-dev --prefer-dist --no-interaction || true

# Opcional: configurar PHP (tiempos y errores)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"     && echo "display_errors=On" >> "$PHP_INI_DIR/php.ini"

CMD ["php-fpm"]

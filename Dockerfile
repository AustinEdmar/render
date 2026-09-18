FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx postgresql-dev git unzip libzip-dev oniguruma-dev icu-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath mbstring intl zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

COPY conf/nginx/nginx-site.conf /etc/nginx/http.d/default.conf
RUN chmod +x scripts/start.sh

EXPOSE 8080
CMD ["/bin/sh", "scripts/start.sh"]
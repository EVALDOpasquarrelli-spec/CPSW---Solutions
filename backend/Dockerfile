FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev

FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","-t","public"]

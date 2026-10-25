# syntax=docker/dockerfile:1
FROM php:8.5.12-apache AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libpq-dev libzip-dev unzip \
    && docker-php-ext-install opcache pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/99-cna.ini
WORKDIR /var/www/html

FROM base AS development
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]

FROM base AS production
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 CMD curl -fsS http://localhost/api/health || exit 1
CMD ["apache2-foreground"]

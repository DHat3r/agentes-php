# Imagen de la app Slim (conciliación). Se construye desde la raíz del monorepo.

FROM composer:2 AS vendor
WORKDIR /repo
COPY packages/agent-core packages/agent-core
COPY apps/conciliacion/composer.json apps/conciliacion/composer.lock apps/conciliacion/
WORKDIR /repo/apps/conciliacion
RUN composer install --no-dev --no-autoloader --prefer-dist --no-interaction
COPY apps/conciliacion/ .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.5-fpm-alpine
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql opcache \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/app
COPY --from=vendor /repo/apps/conciliacion .

# Migra al arrancar y luego deja PHP-FPM como proceso principal.
CMD ["sh", "-c", "php bin/migrate.php && exec php-fpm"]

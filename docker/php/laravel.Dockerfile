# Imagen compartida por las dos apps Laravel (qa-evaluator y tutor).
# Se construye desde la raíz del monorepo:  --build-arg APP_DIR=apps/qa-evaluator

# --- Etapa 1: dependencias con Composer --------------------------------------
FROM composer:2 AS vendor
ARG APP_DIR
WORKDIR /repo

# El path repository apunta a ../../packages/agent-core, así que se copia primero.
COPY packages/agent-core packages/agent-core
COPY ${APP_DIR}/composer.json ${APP_DIR}/composer.lock ${APP_DIR}/
WORKDIR /repo/${APP_DIR}
# Copiar solo composer.* antes del código aprovecha la caché de Docker:
# si no cambian las dependencias, esta capa no se vuelve a construir.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY ${APP_DIR}/ .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- Etapa 2: runtime PHP-FPM ------------------------------------------------
FROM php:8.5-fpm-alpine
ARG APP_DIR

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis opcache \
    # Sin esto PHP-FPM borra las variables de entorno y Laravel no ve la configuración de Docker.
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/app
COPY --from=vendor /repo/${APP_DIR} .
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

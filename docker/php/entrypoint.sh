#!/bin/sh
set -e

# Las APIs no usan sesiones ni cookies cifradas, así que basta una APP_KEY por contenedor.
# En un despliegue real, define APP_KEY en .env para que sea fija.
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
fi

# Solo el contenedor web migra; el worker de la cola usa RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi

exec "$@"

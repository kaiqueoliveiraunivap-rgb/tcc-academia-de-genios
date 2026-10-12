#!/bin/sh
set -eu

APP_PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

# O banco pode levar alguns segundos para ficar disponível no primeiro deploy.
attempt=1
until php /var/www/html/php_appest/tools/instalar.php; do
    if [ "$attempt" -ge 30 ]; then
        echo "Banco indisponível depois de 30 tentativas." >&2
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 2
done

exec apache2-foreground


#!/bin/bash
set -euo pipefail

cd /var/www/html

until [ -f vendor/autoload.php ]; do
    sleep 2
done

until [ -w var/data ]; do
    sleep 2
done

mkdir -p var/pdf

exec runuser -u www-data -- php bin/console messenger:consume async --time-limit=3600 -vv

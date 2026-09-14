#!/bin/bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --prefer-dist --no-interaction

mkdir -p var/cache var/log var/data var/pdf
chown -R www-data:www-data var/cache var/log var/data var/pdf
chmod -R a+rwX var/cache var/log var/pdf

needs_seed=0
if [ ! -s var/data/app.db ]; then
    needs_seed=1
fi

runuser -u www-data -- php bin/console doctrine:migrations:migrate --no-interaction

if [ "${needs_seed}" -eq 1 ]; then
    runuser -u www-data -- php bin/console doctrine:fixtures:load --no-interaction
fi

exec apache2-foreground

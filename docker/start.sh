#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env
[ -d storage ] && chmod -R a+rwX storage
[ -d bootstrap/cache ] && chmod -R a+rwX bootstrap/cache
[ -d vendor ] || composer install --no-interaction
grep -q '^APP_KEY=base64:' .env || php artisan key:generate

exec php-fpm -F

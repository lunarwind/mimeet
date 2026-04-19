#!/bin/sh
set -e

# Ensure Laravel storage directories exist (needed when volume-mounted)
mkdir -p storage/framework/{cache/data,sessions,views,testing} \
         storage/logs \
         storage/app/public \
         bootstrap/cache

touch storage/logs/laravel.log
chown -R www-data:www-data storage bootstrap/cache
chmod 664 storage/logs/laravel.log

exec "$@"

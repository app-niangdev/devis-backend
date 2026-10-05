#!/bin/sh
set -e

cd /var/www

# storage/app est un volume : on recrée l'arborescence attendue au premier démarrage
mkdir -p storage/app/public storage/app/private \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
tries=0
until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" > /dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "PostgreSQL injoignable (${DB_HOST}:${DB_PORT}) après 60 s" >&2
    exit 1
  fi
  echo "En attente de PostgreSQL..."
  sleep 2
done

[ -L public/storage ] || php artisan storage:link

# Configuration figée au démarrage : après un changement du .env, recréer le conteneur (docker compose up -d backend)
php artisan optimize

# Après les commandes artisan (lancées en root) : PHP-FPM tourne en www-data
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"

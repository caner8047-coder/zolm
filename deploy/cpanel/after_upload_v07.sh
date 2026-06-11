#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_DIR"

echo "== ZOLM v0.7 cPanel post-deploy =="
echo "App dir: $APP_DIR"

if [ ! -f artisan ]; then
  echo "artisan bulunamadi. Bu script Laravel proje kokunden calismali."
  exit 1
fi

if [ ! -f .env ]; then
  if [ -f deploy/production/.env.production.example ]; then
    cp deploy/production/.env.production.example .env
    echo ".env yoktu; production orneginden olusturuldu."
    echo "Lutfen DB bilgileri ve APP_KEY degerini doldurup scripti tekrar calistirin."
    exit 1
  fi

  echo ".env bulunamadi."
  exit 1
fi

timestamp="$(date +%F-%H%M%S)"
cp .env ".env.before-v07-$timestamp"
echo ".env yedegi alindi: .env.before-v07-$timestamp"

mkdir -p storage/app/public storage/framework/cache storage/framework/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --optimize-autoloader --no-interaction
elif [ ! -f vendor/autoload.php ]; then
  echo "composer yok ve vendor/autoload.php bulunamadi. Once vendor paketlerini yukleyin."
  exit 1
else
  echo "composer yok; mevcut vendor kullaniliyor."
fi

php artisan storage:link || true

env_value() {
  awk -F= -v key="$1" '$1 == key { value = substr($0, index($0, "=") + 1); gsub(/^"|"$/, "", value); print value; exit }' .env
}

DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

if command -v mysqldump >/dev/null 2>&1 && [ -n "$DB_DATABASE" ] && [ -n "$DB_USERNAME" ]; then
  backup_file="storage/app/zolm-before-v07-$timestamp.sql"
  MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" > "$backup_file"
  echo "DB yedegi alindi: $backup_file"
else
  echo "mysqldump veya DB env eksik; DB yedegi otomatik alinamadi. Devam etmeden manuel yedek alin."
  exit 1
fi

if command -v mysql >/dev/null 2>&1 && [ -f database/sql/v06_to_v07_preflight.sql ]; then
  MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" < database/sql/v06_to_v07_preflight.sql
else
  echo "mysql CLI veya preflight SQL bulunamadi."
  exit 1
fi

php artisan migrate --force

MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" < database/sql/v06_to_v07_preflight.sql

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart || true

php artisan marketplace:health-check --fail-on-warning

echo "== v0.7 post-deploy tamamlandi =="
echo "Son kontrol:"
echo "  https://m.zolm.com.tr/login"
echo "  php artisan marketplace:smoke-test STORE_ID --type=orders --hours=24 --preview=2"
echo "  php artisan marketplace:smoke-test STORE_ID --type=questions --hours=168 --preview=2"

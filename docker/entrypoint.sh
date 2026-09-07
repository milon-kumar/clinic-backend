#!/bin/sh
set -eu

cd /var/www/html

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
i=0
until php -r '
$h = getenv("DB_HOST") ?: "mysql";
$p = getenv("DB_PORT") ?: "3306";
$d = getenv("DB_DATABASE") ?: "zaina_clinic";
$u = getenv("DB_USERNAME") ?: "zaina";
$w = getenv("DB_PASSWORD") ?: "";
try {
    new PDO("mysql:host={$h};port={$p};dbname={$d}", $u, $w, [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    exit(0);
} catch (Throwable $e) {
    exit(1);
}
'; do
  i=$((i + 1))
  if [ "$i" -ge 60 ]; then
    echo "MySQL did not become ready in time."
    exit 1
  fi
  sleep 2
done

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  storage/app/private \
  bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is empty; generating one (set APP_KEY in compose for stable sessions)."
  php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true

if [ "${SEED_ON_START:-false}" = "true" ] && [ ! -f storage/app/.seeded ]; then
  echo "First boot — running database seeders."
  php artisan db:seed --force --no-interaction
  touch storage/app/.seeded
fi

php artisan config:cache --no-interaction >/dev/null 2>&1 || true
php artisan route:cache --no-interaction >/dev/null 2>&1 || true

echo "Laravel listening on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000

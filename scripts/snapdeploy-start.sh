#!/bin/sh
set -eu

cd /app

mkdir -p /tmp
chmod 1777 /tmp || true
export TMPDIR=/tmp

ensure_dir() {
  p="$1"
  if [ -e "$p" ] && [ ! -d "$p" ]; then
    rm -f "$p"
  fi
  mkdir -p "$p"
}

# Keep runtime-writable paths available for Laravel cache, sessions, logs, uploads, and storage links.
ensure_dir storage/app/public
ensure_dir storage/app/images
ensure_dir storage/app/torrents
ensure_dir storage/app/nfo
ensure_dir storage/framework/cache
ensure_dir storage/framework/views
ensure_dir storage/framework/sessions
ensure_dir storage/logs
ensure_dir bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache || true

php artisan storage:link || true

php artisan migrate --force

if [ "${NEXTGN_PREALPHA_DEMO:-false}" = "true" ]; then
  php artisan db:seed --class=StagingDemoSeeder --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

: "${PORT:=10000}"
export PORT

exec frankenphp run --config /app/deploy/frankenphp/Caddyfile

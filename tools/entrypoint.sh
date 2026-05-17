#!/bin/sh
set -eu

cd /app

# Keep temp writes available for PHP and Composer-created caches.
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

require_env() {
  name="$1"
  eval "value=\${$name:-}"
  if [ -z "$value" ]; then
    echo "Missing required environment variable: $name" >&2
    exit 1
  fi
}

# .env is only a local fallback. Production must provide explicit environment.
if [ ! -f .env ] && [ -f .env.example ] && [ "${APP_ENV:-}" != "production" ]; then
  cp .env.example .env
fi

: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
export APP_ENV APP_DEBUG

if [ "$APP_ENV" = "production" ]; then
  if [ "$APP_DEBUG" != "false" ]; then
    echo "Refusing to start production with APP_DEBUG enabled." >&2
    exit 1
  fi

  require_env APP_KEY
  require_env DB_CONNECTION

  case "$DB_CONNECTION" in
    sqlite)
      require_env DB_DATABASE
      ;;
    mysql|mariadb|pgsql|sqlsrv)
      require_env DB_HOST
      require_env DB_DATABASE
      require_env DB_USERNAME
      ;;
  esac
else
  php artisan key:generate --force || true
fi

# Writable runtime paths for cache, sessions, logs, file uploads, and storage links.
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
php artisan config:cache
php artisan route:cache
php artisan view:cache

: "${PORT:=10000}"
exec php -S 0.0.0.0:${PORT} -t public

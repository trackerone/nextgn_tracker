# Dockerfile
FROM php:8.4-cli

ARG APP_UID=1000
ARG APP_GID=1000

# System deps
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libonig-dev libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions som Laravel forventer
RUN docker-php-ext-install \
    zip intl mbstring bcmath pdo pdo_mysql pdo_sqlite opcache

# Runtime user
RUN groupadd --gid ${APP_GID} nextgn \
    && useradd --uid ${APP_UID} --gid nextgn --create-home --shell /bin/sh nextgn

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Sørg for at overlay deployes ind i app-strukturen (din kode bor her)
RUN [ -d /app/overlay ] && cp -R /app/overlay/* /app/ || true

# Installér afhængigheder i build (fail fast!)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Forbered writeable dirs – selvheal hvis 'bootstrap/cache' er file
RUN set -eu; \
    ensure_dir() { p="$1"; if [ -e "$p" ] && [ ! -d "$p" ]; then rm -f "$p"; fi; mkdir -p "$p"; }; \
    ensure_dir storage/app/public; \
    ensure_dir storage/app/images; \
    ensure_dir storage/app/torrents; \
    ensure_dir storage/app/nfo; \
    ensure_dir storage/framework/cache; \
    ensure_dir storage/framework/views; \
    ensure_dir storage/framework/sessions; \
    ensure_dir storage/logs; \
    ensure_dir bootstrap/cache; \
    chown -R nextgn:nextgn /app; \
    chmod -R u+rwX,g+rwX storage bootstrap/cache


# (Valgfrit) PHP-ini
# COPY tools/php.ini /usr/local/etc/php/conf.d/zzz-custom.ini

# Render lytter på $PORT; vi sætter en default
ENV PORT=10000
EXPOSE 10000

USER nextgn

# Runtime sker i entrypoint
CMD ["sh","/app/tools/entrypoint.sh"]


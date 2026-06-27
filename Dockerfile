# Dockerfile
FROM node:24-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.ts tsconfig.json postcss.config.js tailwind.config.js ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-bookworm

ARG APP_UID=1000
ARG APP_GID=1000

# System deps used by Composer and runtime tooling.
RUN apt-get update && apt-get install -y \
    git unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Laravel, application dependencies, and supported databases.
RUN install-php-extensions \
    zip \
    intl \
    mbstring \
    bcmath \
    pdo_mysql \
    pdo_sqlite \
    opcache \
    gd \
    curl \
    dom

# Runtime user.
RUN groupadd --gid ${APP_GID} nextgn \
    && useradd --uid ${APP_UID} --gid nextgn --create-home --shell /bin/sh nextgn

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Install production dependencies during the image build so failures happen early.
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Copy prebuilt Vite assets from the Node build stage for Blade UI navigation.
COPY --from=frontend /app/public/build /app/public/build

# Prepare writable dirs; self-heal if bootstrap/cache is accidentally a file.
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
    ensure_dir /config/caddy; \
    ensure_dir /data/caddy; \
    chown -R nextgn:nextgn /app /config/caddy /data/caddy; \
    chmod -R u+rwX,g+rwX storage bootstrap/cache /config/caddy /data/caddy

# Runtime platforms can provide $PORT; keep a local default.
ENV PORT=10000
EXPOSE 10000

USER nextgn

# Runtime starts through the SnapDeploy pre-alpha bootstrap script.
CMD ["sh", "/app/scripts/snapdeploy-start.sh"]

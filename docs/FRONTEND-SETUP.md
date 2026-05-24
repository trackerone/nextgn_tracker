# Frontend Setup

The NextGN Tracker UI uses Vite + React + Tailwind. Keep Laravel running while Vite runs so browser/API behavior matches normal local usage.

## Requirements

- Node.js **20.x–25.x**
- npm **10+**
- Installed PHP dependencies (`composer install`)

## Install frontend dependencies

For local development:

```bash
npm install
```

For CI/production repeatable installs:

```bash
npm ci
```

Common failure:
- Lock mismatch during `npm ci`: regenerate lock file in a controlled update PR using `npm install`, commit `package-lock.json`, then rerun `npm ci`.

## Development workflow

Start backend and frontend in separate terminals.

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
npm run dev
```

## Production asset build

```bash
npm ci
npm run build
```

After deployment/build updates, clear stale Laravel views/config if old assets are still referenced:

```bash
php artisan config:clear
php artisan view:clear
```

## Troubleshooting

### Vite cannot resolve modules

```bash
rm -rf node_modules
npm install
npm run build
```

### Wrong Node version

```bash
node -v
npm -v
```

If out of supported range, install a supported Node LTS before retrying.

### Old manifest/assets still served

```bash
php artisan optimize:clear
npm run build
```

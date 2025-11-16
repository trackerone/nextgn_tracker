# Frontend Setup

The NextGN Tracker UI uses [Vite](https://vitejs.dev/) with the official Laravel plugin and a React + Tailwind stack. Keep the backend running with `php artisan serve` (or Sail/Herd) while you work locally so Vite can proxy API calls during development.

## Requirements

- Node.js 20.x–25.x (LTS)
- npm 10+
- PHP/Laravel app running locally (e.g. `php artisan serve --host=0.0.0.0 --port=8000`)

## Installation

```bash
npm install
```

This installs the Vite toolchain plus TailwindCSS, shadcn/ui utilities, and the Laravel Vite plugin. Dependencies are defined in `package.json` and TypeScript is configured via `tsconfig.json`.

## Development workflow

```bash
npm run dev
```

- Starts Vite in development mode with React Fast Refresh.
- Serves assets from memory while Laravel renders views.
- Watches Blade templates under `resources/views` and TypeScript/TSX sources in `resources/js`.

Main entrypoints declared in `vite.config.ts`:

- `resources/css/app.css` (Tailwind layers + global tokens)
- `resources/js/app.tsx` (React forum + messaging UI)

Blade templates load these via `@vite([...])`. The forum UI expects session data to be exposed as `window.__APP__` (see `welcome.blade.php`).

## Production build

```bash
npm run build
```

- Generates versioned assets in `public/build`.
- Use `php artisan config:clear && php artisan view:clear` after deploying to ensure Laravel picks up the new manifest.

You can review the built bundle locally with:

```bash
npm run preview
```

This serves the Vite production output for quick smoke-testing.

## Tailwind and PostCSS

- Tailwind is configured in `tailwind.config.js` to scan Blade and TS/TSX files.
- PostCSS (`postcss.config.js`) enables `tailwindcss` + `autoprefixer` only—no legacy Mix plugins remain.

## Notes

- Do not commit `public/build` outputs; CI/CD should run `npm run build` during deploys.
- If you add new entrypoints, register them in `vite.config.ts` and load them with `@vite()` in the relevant Blade templates.

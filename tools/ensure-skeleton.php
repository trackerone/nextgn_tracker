<?php

// tools/ensure-skeleton.php
declare(strict_types=1);

$root = rtrim(getenv('APP_ROOT') ?: '/app', '/');

function put($path, $contents)
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (! file_exists($path)) {
        file_put_contents($path, $contents);
    }
}

function ensureDir($path)
{
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
    @chmod($path, 0775);
}

// 1) bootstrap/app.php
$bootstrapApp = <<<'PHP'
<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware) {
        //
    })
    ->withExceptions(function (\Illuminate\Foundation\Configuration\Exceptions $exceptions) {
        //
    })->create();
PHP;
put("$root/bootstrap/app.php", $bootstrapApp);

// 2) public/index.php
$publicIndex = <<<'PHP'
<?php

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
PHP;
put("$root/public/index.php", $publicIndex);

// 3) bootstrap/cache + writables
ensureDir("$root/bootstrap/cache");
ensureDir("$root/storage");
ensureDir("$root/storage/framework");
ensureDir("$root/storage/framework/cache");
ensureDir("$root/storage/framework/views");
ensureDir("$root/storage/framework/sessions");
@chmod("$root/storage", 0775);

// 4) routes/web.php (fallback "it works")
put("$root/routes/web.php", "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/', fn() => view('welcome'));\n");

// 5) resources/views/welcome.blade.php
$welcome = <<<'BLADE'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>PU-239 NextGN</title></head>
<body style="font-family:system-ui,Segoe UI,Arial,sans-serif;padding:3rem">
<h1>PU-239 NextGN is running 🎉</h1>
<p>Environment: {{ app()->environment() }} — Laravel {{ app()->version() }}</p>
</body></html>
BLADE;
put("$root/resources/views/welcome.blade.php", $welcome);

// 6) .env.example + .env
$envExample = <<<'ENV'
APP_NAME=PU239
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL:-http://localhost}
LOG_CHANNEL=stack
LOG_LEVEL=info
ENV;
put("$root/.env.example", $envExample);

$envPath = "$root/.env";
if (! file_exists($envPath)) {
    @copy("$root/.env.example", $envPath);
}

// 7) Generate APP_KEY if empty
$env = file_exists($envPath) ? file_get_contents($envPath) : '';
if (strpos($env, 'APP_KEY=') !== false && preg_match('/^APP_KEY\s*=\s*$/m', $env)) {
    $artisan = "$root/artisan";
    if (file_exists($artisan)) {
        @passthru("php $artisan key:generate --force 2>/dev/null");
    } else {
        $key = 'base64:'.base64_encode(random_bytes(32));
        $env = preg_replace('/^APP_KEY\s*=\s*$/m', "APP_KEY={$key}", $env);
        file_put_contents($envPath, $env);
    }
}

echo "[ensure] skeleton ensured\n";

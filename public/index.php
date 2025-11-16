<?php

declare(strict_types=1);

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

$app->handleRequest(
    $request = Illuminate\Http\Request::capture()
);

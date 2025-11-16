<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'api.hmac'])->group(static function (): void {});

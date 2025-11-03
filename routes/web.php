<?php

declare(strict_types=1);

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/health', HealthCheckController::class)->name('health.index');

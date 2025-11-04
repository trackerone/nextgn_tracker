<?php

declare(strict_types=1);

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/health', HealthCheckController::class)->name('health.index');

Route::middleware(['auth', 'verified', 'role.min:10'])
    ->get('/admin', static fn () => response()->json(['message' => 'Admin area']))
    ->name('demo.admin');

Route::middleware(['auth', 'verified', 'role.min:8'])
    ->get('/mod', static fn () => response()->json(['message' => 'Moderator area']))
    ->name('demo.mod');

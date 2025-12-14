<?php

declare(strict_types=1);

use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TrackerController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

Route::get('/', fn (): View => view('home'));
Route::get('/health', fn (): Response => response('ok', Response::HTTP_OK));

Route::get('/status', function (): JsonResponse {
    $checks = [
        'app' => 'OK',
        'db' => 'UNKNOWN',
        'migrations' => 'UNKNOWN',
    ];

    try {
        DB::select('SELECT 1');
        $checks['db'] = 'Connected';
    } catch (Throwable $exception) {
        $checks['db'] = 'ERROR';
    }

    try {
        $migrations = DB::table('migrations')->count();
        $checks['migrations'] = $migrations >= 0 ? 'OK' : 'MISSING';
    } catch (Throwable $exception) {
        $checks['migrations'] = 'ERROR';
    }

    return response()->json([
        'app' => 'Laravel',
        'status' => $checks,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::match(['GET', 'POST'], '/announce', [TrackerController::class, 'announce']);
Route::match(['GET', 'POST'], '/scrape', [TrackerController::class, 'scrape']);

// Torrents (tests expect authenticated JSON endpoints)
Route::middleware(['auth'])->group(function (): void {
    Route::get('/torrents', [TorrentController::class, 'index'])->name('torrents.index');
    Route::get('/torrents/{torrent:slug}', [TorrentController::class, 'show'])->name('torrents.show');
});

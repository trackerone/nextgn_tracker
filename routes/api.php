<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TorrentBrowseController;
use App\Http\Controllers\Api\TorrentDetailsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'api.hmac'])->group(function (): void {
    Route::get('/user', static function (Request $request) {
        return response()->json([
            'id' => $request->user()?->id,
            'name' => $request->user()?->name,
            'email' => $request->user()?->email,
        ]);
    })->name('api.user');
});

Route::middleware(['api', 'auth'])->group(function (): void {
    Route::get('/torrents', [TorrentBrowseController::class, 'index'])->name('api.torrents.index');
    Route::get('/torrents/{torrent}', [TorrentDetailsController::class, 'show'])->name('api.torrents.show');
});

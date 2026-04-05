<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ModerationUploadsController;
use App\Http\Controllers\Api\MyUploadsController;
use App\Http\Controllers\Api\TorrentBrowseController;
use App\Http\Controllers\Api\TorrentDetailsController;
use App\Http\Controllers\Api\TorrentDownloadController;
use App\Http\Controllers\Api\UploadSubmissionController;
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
    Route::get('/torrents/{torrent}/download', TorrentDownloadController::class)->name('api.torrents.download');

    Route::post('/uploads', [UploadSubmissionController::class, 'store'])->name('api.uploads.store');
    Route::get('/my/uploads', [MyUploadsController::class, 'index'])->name('api.my.uploads');

    Route::get('/moderation/uploads', [ModerationUploadsController::class, 'index'])->name('api.moderation.uploads.index');
    Route::post('/moderation/uploads/{torrent}/approve', [ModerationUploadsController::class, 'approve'])
        ->name('api.moderation.uploads.approve');
    Route::post('/moderation/uploads/{torrent}/reject', [ModerationUploadsController::class, 'reject'])
        ->name('api.moderation.uploads.reject');
});

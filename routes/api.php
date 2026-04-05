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

$torrentBrowseThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_browse', '60,1'));
$torrentDetailsThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_details', '90,1'));
$torrentDownloadThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_download', '45,1'));
$moderationThrottle = sprintf(
    'throttle:%s',
    config('security.rate_limits.torrent_moderation', config('security.rate_limits.moderation', '60,1'))
);

Route::middleware(['api', 'api.hmac'])->group(function (): void {
    Route::get('/user', static function (Request $request) {
        return response()->json([
            'id' => $request->user()?->id,
            'name' => $request->user()?->name,
            'email' => $request->user()?->email,
        ]);
    })->name('api.user');
});

Route::middleware(['api', 'auth'])->group(function () use ($torrentBrowseThrottle, $torrentDetailsThrottle, $torrentDownloadThrottle, $moderationThrottle): void {
    Route::get('/torrents', [TorrentBrowseController::class, 'index'])
        ->middleware($torrentBrowseThrottle)
        ->name('api.torrents.index');
    Route::get('/torrents/{torrent}', [TorrentDetailsController::class, 'show'])
        ->middleware($torrentDetailsThrottle)
        ->name('api.torrents.show');
    Route::get('/torrents/{torrent}/download', TorrentDownloadController::class)
        ->middleware($torrentDownloadThrottle)
        ->name('api.torrents.download');

    Route::post('/uploads', [UploadSubmissionController::class, 'store'])->name('api.uploads.store');
    Route::get('/my/uploads', [MyUploadsController::class, 'index'])->name('api.my.uploads');

    Route::get('/moderation/uploads', [ModerationUploadsController::class, 'index'])
        ->middleware($moderationThrottle)
        ->name('api.moderation.uploads.index');
    Route::post('/moderation/uploads/{torrent}/approve', [ModerationUploadsController::class, 'approve'])
        ->middleware($moderationThrottle)
        ->name('api.moderation.uploads.approve');
    Route::post('/moderation/uploads/{torrent}/reject', [ModerationUploadsController::class, 'reject'])
        ->middleware($moderationThrottle)
        ->name('api.moderation.uploads.reject');
});

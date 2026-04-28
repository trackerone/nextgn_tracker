<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\MetadataProviderSettingsController;
use App\Http\Controllers\Api\Admin\TrackerRatioSettingsController;
use App\Http\Controllers\Api\ModerationUploadsController;
use App\Http\Controllers\Api\MyStatsController;
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

Route::middleware(['api', 'auth', 'role.level:admin'])->prefix('admin/settings/tracker')->group(function (): void {
    Route::get('/ratio', [TrackerRatioSettingsController::class, 'show'])
        ->name('api.admin.settings.tracker.ratio.show');

    Route::post('/ratio', [TrackerRatioSettingsController::class, 'update'])
        ->name('api.admin.settings.tracker.ratio.update');
});

Route::middleware(['api', 'auth', 'role.level:admin'])->prefix('admin/settings/metadata')->group(function (): void {
    Route::get('/providers', [MetadataProviderSettingsController::class, 'show'])
        ->name('api.admin.settings.metadata.providers.show');

    Route::post('/providers', [MetadataProviderSettingsController::class, 'update'])
        ->name('api.admin.settings.metadata.providers.update');
});

Route::middleware(['api', 'auth'])->group(function (): void {
    Route::get('/torrents', [TorrentBrowseController::class, 'index'])
        ->middleware('throttle:torrent-browse')
        ->name('api.torrents.index');

    Route::get('/torrents/{torrent}', [TorrentDetailsController::class, 'show'])
        ->middleware('throttle:torrent-details')
        ->name('api.torrents.show');

    Route::get('/torrents/{torrent}/download', TorrentDownloadController::class)
        ->middleware('throttle:torrent-download')
        ->name('api.torrents.download');

    Route::post('/uploads', [UploadSubmissionController::class, 'store'])
        ->name('api.uploads.store');

    Route::get('/my/uploads', [MyUploadsController::class, 'index'])
        ->name('api.my.uploads');

    Route::get('/me/stats', MyStatsController::class)
        ->name('api.me.stats');

    Route::get('/moderation/uploads', [ModerationUploadsController::class, 'index'])
        ->middleware('staff')
        ->middleware('throttle:torrent-moderation')
        ->name('api.moderation.uploads.index');

    Route::post('/moderation/uploads/{torrent}/approve', [ModerationUploadsController::class, 'approve'])
        ->middleware('staff')
        ->middleware('throttle:torrent-moderation')
        ->name('api.moderation.uploads.approve');

    Route::post('/moderation/uploads/{torrent}/reject', [ModerationUploadsController::class, 'reject'])
        ->middleware('staff')
        ->middleware('throttle:torrent-moderation')
        ->name('api.moderation.uploads.reject');
});

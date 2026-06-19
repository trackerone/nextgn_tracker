<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\MetadataCredentialSettingsController;
use App\Http\Controllers\Api\Admin\MetadataProviderSettingsController;
use App\Http\Controllers\Api\Admin\TrackerRatioSettingsController;
use App\Http\Controllers\Api\DiscoveryExplainabilityController;
use App\Http\Controllers\Api\DiscoveryHealthController;
use App\Http\Controllers\Api\DiscoveryHomeController;
use App\Http\Controllers\Api\DiscoveryMetadataController;
use App\Http\Controllers\Api\DiscoveryOperationsActionHintController;
use App\Http\Controllers\Api\DiscoveryOperationsDrilldownController;
use App\Http\Controllers\Api\DiscoveryOperationsOverviewController;
use App\Http\Controllers\Api\DiscoveryOperationsPriorityController;
use App\Http\Controllers\Api\DiscoveryOperationsReviewQueueController;
use App\Http\Controllers\Api\DiscoveryPopularMetadataController;
use App\Http\Controllers\Api\DiscoveryRssSuggestionsController;
use App\Http\Controllers\Api\DiscoverySummaryController;
use App\Http\Controllers\Api\DiscoveryTrendingController;
use App\Http\Controllers\Api\DiscoveryWatchPresetSuggestionsController;
use App\Http\Controllers\Api\ModerationUploadsController;
use App\Http\Controllers\Api\MyStatsController;
use App\Http\Controllers\Api\MyUploadsController;
use App\Http\Controllers\Api\RecommendationCandidatesController;
use App\Http\Controllers\Api\RecommendationEngineController;
use App\Http\Controllers\Api\RecommendationExplainabilityController;
use App\Http\Controllers\Api\RecommendationHealthController;
use App\Http\Controllers\Api\RecommendationOutputController;
use App\Http\Controllers\Api\RecommendationPreviewController;
use App\Http\Controllers\Api\RecommendationSignalsController;
use App\Http\Controllers\Api\RecommendationTorrentsController;
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

    Route::get('/credentials/status', [MetadataCredentialSettingsController::class, 'status'])
        ->name('api.admin.settings.metadata.credentials.status');

    Route::put('/credentials/{provider}', [MetadataCredentialSettingsController::class, 'set'])
        ->name('api.admin.settings.metadata.credentials.set');

    Route::delete('/credentials/{provider}/{field}', [MetadataCredentialSettingsController::class, 'clear'])
        ->name('api.admin.settings.metadata.credentials.clear');
});

Route::middleware(['api', 'auth'])->group(function (): void {
    Route::get('/discovery/home', DiscoveryHomeController::class)
        ->name('api.discovery.home');

    Route::get('/discovery/health', DiscoveryHealthController::class)
        ->name('api.discovery.health');

    Route::get('/discovery/explainability', DiscoveryExplainabilityController::class)
        ->name('api.discovery.explainability');

    Route::get('/discovery/operations-overview', DiscoveryOperationsOverviewController::class)
        ->name('api.discovery.operations-overview');

    Route::get('/discovery/operations-priorities', DiscoveryOperationsPriorityController::class)
        ->name('api.discovery.operations-priorities');

    Route::get('/discovery/operations-drilldown', DiscoveryOperationsDrilldownController::class)
        ->name('api.discovery.operations-drilldown');

    Route::get('/discovery/operations-action-hints', DiscoveryOperationsActionHintController::class)
        ->name('api.discovery.operations-action-hints');

    Route::get('/discovery/operations-review-queue', DiscoveryOperationsReviewQueueController::class)
        ->name('api.discovery.operations-review-queue');

    Route::get('/discovery/metadata', DiscoveryMetadataController::class)
        ->name('api.discovery.metadata');

    Route::get('/discovery/popular', DiscoveryPopularMetadataController::class)
        ->name('api.discovery.popular');

    Route::get('/discovery/rss-suggestions', DiscoveryRssSuggestionsController::class)
        ->name('api.discovery.rss-suggestions');

    Route::get('/discovery/watch-preset-suggestions', DiscoveryWatchPresetSuggestionsController::class)
        ->name('api.discovery.watch-preset-suggestions');

    Route::get('/discovery/summary', DiscoverySummaryController::class)
        ->name('api.discovery.summary');

    Route::get('/discovery/trending', DiscoveryTrendingController::class)
        ->name('api.discovery.trending');

    Route::get('/recommendations/signals', RecommendationSignalsController::class)
        ->name('api.recommendations.signals');

    Route::get('/recommendations/engine', RecommendationEngineController::class)
        ->name('api.recommendations.engine');

    Route::get('/recommendations/candidates', RecommendationCandidatesController::class)
        ->name('api.recommendations.candidates');

    Route::get('/recommendations/output', RecommendationOutputController::class)
        ->name('api.recommendations.output');

    Route::get('/recommendations/preview', RecommendationPreviewController::class)
        ->name('api.recommendations.preview');

    Route::get('/recommendations/torrents', RecommendationTorrentsController::class)
        ->name('api.recommendations.torrents');

    Route::get('/recommendations/health', RecommendationHealthController::class)
        ->name('api.recommendations.health');

    Route::get('/recommendations/explainability', RecommendationExplainabilityController::class)
        ->name('api.recommendations.explainability');

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
        ->middleware('throttle:torrent-upload')
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

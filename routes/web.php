<?php

declare(strict_types=1);

use App\Http\Controllers\AccountInviteController;
use App\Http\Controllers\AccountRssController;
use App\Http\Controllers\AccountRssPresetController;
use App\Http\Controllers\AccountSnatchController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InviteAdminController;
use App\Http\Controllers\Admin\RatioSettingsController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyUploadsController;
use App\Http\Controllers\PersonalizedDiscoveryController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\RssPresetFeedController;
use App\Http\Controllers\RssTorrentDownloadController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\Sysop\OperationsDashboardController;
use App\Http\Controllers\Sysop\RuntimeJobToggleController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentFollowController;
use App\Http\Controllers\TorrentModerationController;
use App\Http\Controllers\TorrentUploadController;
use Illuminate\Support\Facades\Route;

$adminThrottle = sprintf('throttle:%s', config('security.rate_limits.admin', '30,1'));
$searchThrottle = sprintf('throttle:%s', config('security.rate_limits.search', '30,1'));
$torrentBrowseThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_browse', '60,1'));
$torrentDetailsThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_details', '90,1'));
$torrentDownloadThrottle = sprintf('throttle:%s', config('security.rate_limits.torrent_download', '45,1'));
$moderationThrottle = sprintf(
    'throttle:%s',
    config('security.rate_limits.torrent_moderation', config('security.rate_limits.moderation', '60,1'))
);

/*
|--------------------------------------------------------------------------
| AUTH (tests expect these exact flows)
|--------------------------------------------------------------------------
*/
Route::get('/', static fn () => redirect('/login'));

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->get('/home', HomeController::class)->name('home');

/*
|--------------------------------------------------------------------------
| REGISTRATION
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware(sprintf('throttle:%s', config('security.rate_limits.register', '3,60')));
});

/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/
Route::get('/health', HealthCheckController::class)->name('health.index');
Route::get('/rss/{token}', RssFeedController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('rss.feed');
Route::get('/rss/{token}/presets/{preset}', RssPresetFeedController::class)
    ->where(['token' => '[A-Za-z0-9]+', 'preset' => '[0-9a-fA-F-]{36}'])
    ->name('rss.presets.feed');
Route::get('/rss/{token}/download/{torrent}', RssTorrentDownloadController::class)
    ->middleware($torrentDownloadThrottle)
    ->where(['token' => '[A-Za-z0-9]+', 'torrent' => '[0-9]+'])
    ->name('rss.torrents.download');

/*
|--------------------------------------------------------------------------
| ROLE ACCESS TEST ENDPOINTS
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.level:admin', $adminThrottle])
    ->get('/admin', DashboardController::class)
    ->name('demo.admin');

Route::middleware(['auth', 'role.level:mod'])
    ->get('/mod', static fn () => response()->json(['message' => 'Moderator area']))
    ->name('demo.mod');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.level:admin', $adminThrottle])->group(function (): void {
    Route::get('/admin/settings/ratio', [RatioSettingsController::class, 'edit'])
        ->name('admin.settings.ratio.edit');
    Route::patch('/admin/settings/ratio', [RatioSettingsController::class, 'update'])
        ->name('admin.settings.ratio.update');
    Route::view('/admin/settings/metadata', 'admin.settings.metadata')
        ->name('admin.settings.metadata.edit');
});

// Invites are intended to be accessible to staff (tests assert staff access).
Route::middleware(['auth', 'staff', $adminThrottle])->group(function (): void {
    Route::get('/admin/invites', [InviteAdminController::class, 'index'])->name('admin.invites.index');
    Route::post('/admin/invites', [InviteAdminController::class, 'store'])->name('admin.invites.store');
});

Route::middleware(['auth', 'staff', 'can:view-logs', $adminThrottle])
    ->prefix('admin/logs')
    ->name('admin.logs.')
    ->group(function (): void {
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit/{log}', [AuditLogController::class, 'show'])->name('audit.show');
        Route::get('/security', [SecurityEventController::class, 'index'])->name('security.index');
        Route::get('/security/{event}', [SecurityEventController::class, 'show'])->name('security.show');
    });

Route::middleware(['auth', 'role.level:sysop', $adminThrottle])->prefix('sysop')->name('sysop.')->group(function (): void {
    Route::get('/operations', OperationsDashboardController::class)->name('operations.index');
    Route::post('/operations/runtime-jobs/toggle', RuntimeJobToggleController::class)->name('operations.runtime-jobs.toggle');
});
Route::middleware(['auth', 'staff', 'can:isAdmin', $adminThrottle])->group(function (): void {
    Route::patch('/admin/users/{user}/role', [UserRoleController::class, 'update'])
        ->name('admin.users.role.update');
});

/*
|--------------------------------------------------------------------------
| STAFF TORRENT MODERATION
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff', $moderationThrottle])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/torrents/moderation', [TorrentModerationController::class, 'index'])
        ->name('torrents.moderation.index');

    Route::post('/torrents/{torrent}/approve', [TorrentModerationController::class, 'approve'])
        ->name('torrents.approve');

    Route::post('/torrents/{torrent}/reject', [TorrentModerationController::class, 'reject'])
        ->name('torrents.reject');

    Route::post('/torrents/{torrent}/soft-delete', [TorrentModerationController::class, 'softDelete'])
        ->name('torrents.soft_delete');
});

/*
|--------------------------------------------------------------------------
| FORUM
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.min:1', 'throttle:60,1'])->group(function (): void {
    Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
    Route::get('/topics/{topic:slug}', [TopicController::class, 'show'])->name('topics.show');

    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/topics/{topic}/posts', [PostController::class, 'store'])->name('topics.posts.store');
});

Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::patch('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');

    Route::post('/topics/{topic}/lock', [TopicController::class, 'toggleLock'])
        ->middleware('role.min:8')
        ->name('topics.lock');

    Route::post('/topics/{topic}/pin', [TopicController::class, 'togglePin'])
        ->middleware('role.min:8')
        ->name('topics.pin');

    Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])
        ->middleware('role.min:10')
        ->name('topics.destroy');

    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/restore', [PostController::class, 'restore'])->withTrashed()->name('posts.restore');
});

/*
|--------------------------------------------------------------------------
| PRIVATE MESSAGES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role.min:1'])->group(function (): void {
    Route::get('/pm', [PrivateMessageController::class, 'index'])->name('pm.index');
    Route::get('/pm/{conversation}', [PrivateMessageController::class, 'show'])->name('pm.show');

    Route::post('/pm', [PrivateMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pm.store');

    Route::post('/pm/{conversation}/messages', [ConversationMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pm.messages.store');
});

/*
|--------------------------------------------------------------------------
| TORRENTS
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () use ($searchThrottle, $torrentBrowseThrottle, $torrentDetailsThrottle, $torrentDownloadThrottle): void {
    Route::get('/torrents', [TorrentController::class, 'index'])
        ->middleware([$searchThrottle, $torrentBrowseThrottle])
        ->name('torrents.index');

    Route::get('/torrents/{torrent}', [TorrentController::class, 'show'])
        ->middleware($torrentDetailsThrottle)
        ->name('torrents.show');

    Route::get('/torrents/{torrent}/download', [TorrentDownloadController::class, 'download'])
        ->middleware($torrentDownloadThrottle)
        ->name('torrents.download');

    Route::get('/torrents/{torrent}/magnet', [TorrentDownloadController::class, 'magnet'])
        ->middleware($torrentDownloadThrottle)
        ->name('torrents.magnet');
});

/*
|--------------------------------------------------------------------------
| TORRENT UPLOAD + ACCOUNT
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function (): void {
    Route::get('/torrents/upload', [TorrentUploadController::class, 'create'])->name('torrents.upload');
    Route::post('/torrents', [TorrentUploadController::class, 'store'])
        ->middleware('throttle:torrent-upload')
        ->name('torrents.store');
    Route::post('/torrents/{torrent}/follow', [TorrentFollowController::class, 'storeFromTorrent'])->name('torrents.follow.store');
    Route::get('/my/uploads', [MyUploadsController::class, 'index'])->name('my.uploads');
    Route::get('/my/discovery', PersonalizedDiscoveryController::class)->name('my.discovery');
    Route::get('/my/follows', [TorrentFollowController::class, 'index'])->name('my.follows');
    Route::post('/my/follows', [TorrentFollowController::class, 'store'])->name('my.follows.store');

    Route::get('/account/snatches', [AccountSnatchController::class, 'index'])->name('account.snatches');
    Route::get('/account/invites', [AccountInviteController::class, 'index'])->name('account.invites');
    Route::get('/account/rss', [AccountRssController::class, 'index'])->name('account.rss.index');
    Route::post('/account/rss/rotate', [AccountRssController::class, 'rotate'])->name('account.rss.rotate');
    Route::get('/account/rss/presets/create', [AccountRssPresetController::class, 'create'])->name('account.rss.presets.create');
    Route::post('/account/rss/presets', [AccountRssPresetController::class, 'store'])->name('account.rss.presets.store');
    Route::get('/account/rss/presets/{preset}/edit', [AccountRssPresetController::class, 'edit'])->whereNumber('preset')->name('account.rss.presets.edit');
    Route::patch('/account/rss/presets/{preset}', [AccountRssPresetController::class, 'update'])->whereNumber('preset')->name('account.rss.presets.update');
    Route::delete('/account/rss/presets/{preset}', [AccountRssPresetController::class, 'destroy'])->whereNumber('preset')->name('account.rss.presets.destroy');
});

Route::middleware(['auth', 'staff', $moderationThrottle])->get('/moderation/uploads', [TorrentModerationController::class, 'index'])
    ->name('moderation.uploads');

/*
|--------------------------------------------------------------------------
| API KEYS
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('account/api-keys')->name('account.api-keys.')->group(function (): void {
    Route::get('/', [ApiKeyController::class, 'index'])->name('index');
    Route::post('/', [ApiKeyController::class, 'store'])->name('store');
    Route::delete('/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->whereNumber('apiKey')
        ->name('destroy');
});

/*
|--------------------------------------------------------------------------
| TRACKER ENDPOINTS
|--------------------------------------------------------------------------
*/
Route::middleware(['throttle:120,1', 'tracker.validate-announce'])
    ->get('/announce/{passkey}', AnnounceController::class)
    ->name('announce');

Route::middleware(['throttle:120,1'])
    ->get('/scrape/{passkey}', ScrapeController::class)
    ->name('scrape');

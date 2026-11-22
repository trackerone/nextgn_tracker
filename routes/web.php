<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountInviteController;
use App\Http\Controllers\AccountNotificationController;
use App\Http\Controllers\AccountPeopleController;
use App\Http\Controllers\AccountRssController;
use App\Http\Controllers\AccountRssPresetController;
use App\Http\Controllers\AccountSnatchController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InviteAdminController;
use App\Http\Controllers\Admin\RatioSettingsController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\UserNameController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\AlphaFeedbackController;
use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Auth\AdminTwoFactorChallengeController;
use App\Http\Controllers\Auth\AdminTwoFactorSetupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\HnrController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyUploadsController;
use App\Http\Controllers\NotificationWatchPresetController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\RssFeedController;
use App\Http\Controllers\RssPresetFeedController;
use App\Http\Controllers\RssTorrentDownloadController;
use App\Http\Controllers\SavedIntentController;
use App\Http\Controllers\ScrapeController;
use App\Http\Controllers\StaffAlphaFeedbackController;
use App\Http\Controllers\Sysop\CategorySettingsController;
use App\Http\Controllers\Sysop\HnrSettingsController;
use App\Http\Controllers\Sysop\LogCenterController;
use App\Http\Controllers\Sysop\OperationsAlertActionController;
use App\Http\Controllers\Sysop\OperationsDashboardController;
use App\Http\Controllers\Sysop\OperationsSupportBundleController;
use App\Http\Controllers\Sysop\PermissionsController;
use App\Http\Controllers\Sysop\RatioCreditController;
use App\Http\Controllers\Sysop\RegistrationSettingsController;
use App\Http\Controllers\Sysop\RuntimeJobToggleController;
use App\Http\Controllers\Sysop\SiteSettingsController;
use App\Http\Controllers\Sysop\TorrentMetadataSourceController;
use App\Http\Controllers\Sysop\UploadLimitsController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentFollowController;
use App\Http\Controllers\TorrentModerationController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WatchCenterController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
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

Route::middleware('guest')->group(function (): void {
    Route::get('/two-factor-challenge', [AdminTwoFactorChallengeController::class, 'show'])
        ->name('admin-two-factor.challenge');
    Route::post('/two-factor-challenge', [AdminTwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:admin-two-factor')
        ->name('admin-two-factor.challenge.store');
});

Route::middleware('auth')->prefix('security/two-factor')->group(function (): void {
    Route::get('/', [AdminTwoFactorSetupController::class, 'show'])
        ->name('admin-two-factor.setup');
    Route::post('/enable', [AdminTwoFactorSetupController::class, 'enable'])
        ->name('admin-two-factor.enable');
    Route::post('/confirm', [AdminTwoFactorSetupController::class, 'confirm'])
        ->name('admin-two-factor.confirm');
});

Route::middleware('auth')->get('/home', HomeController::class)->name('home');

Route::middleware(['auth', 'throttle:6,1'])->group(function (): void {
    Route::post('/email/verification-notification', static function (Request $request) {
        $user = $request->user();
        if ($user !== null && $user->hasVerifiedEmail() === false) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Check your inbox for the verification link.');
    })->name('verification.send');

    Route::get('/email/verify/{id}/{hash}', static function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('topics.index')->with('status', 'Email verified.');
    })->middleware('signed')->name('verification.verify');
});

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
    ->middleware('throttle:30,1')
    ->where('token', '[A-Za-z0-9]+')
    ->name('rss.feed');
Route::get('/rss/{token}/presets/{preset}', RssPresetFeedController::class)
    ->middleware('throttle:30,1')
    ->where(['token' => '[A-Za-z0-9]+', 'preset' => '[0-9a-fA-F-]{36}'])
    ->name('rss.presets.feed');
Route::get('/rss/{token}/download/{torrent}', RssTorrentDownloadController::class)
    ->middleware($torrentDownloadThrottle)
    ->where(['token' => '[A-Za-z0-9]+', 'torrent' => '[0-9]+'])
    ->name('rss.torrents.download');
Route::get('/users/{user:public_slug}', [UserProfileController::class, 'show'])
    ->where('user', '(?![0-9]+$)[A-Za-z0-9][A-Za-z0-9_-]*')
    ->name('users.show');

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
    Route::view('/admin/discovery', 'admin.discovery')->name('admin.discovery');
    Route::get('/admin/settings/ratio', [RatioSettingsController::class, 'edit'])
        ->name('admin.settings.ratio.edit');
    Route::patch('/admin/settings/ratio', [RatioSettingsController::class, 'update'])
        ->name('admin.settings.ratio.update');
    Route::view('/admin/settings/metadata', 'admin.settings.metadata')
        ->name('admin.settings.metadata.edit');
});

// Staff may review all invitations; issuance is separately delegated by Sysop.
Route::middleware(['auth', 'staff', $adminThrottle])->group(function (): void {
    Route::get('/admin/invites', [InviteAdminController::class, 'index'])->name('admin.invites.index');
    Route::post('/admin/invites', [InviteAdminController::class, 'store'])
        ->middleware('permission:invites.create')->name('admin.invites.store');
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
    Route::post('/torrents/{torrent:id}/metadata-source', TorrentMetadataSourceController::class)
        ->whereNumber('torrent')->name('torrents.metadata-source');
    Route::get('/hnr', [HnrSettingsController::class, 'edit'])->name('hnr.edit');
    Route::patch('/hnr', [HnrSettingsController::class, 'update'])->name('hnr.update');
    Route::post('/hnr/obligations/{obligation}/waive', [HnrSettingsController::class, 'waive'])->whereNumber('obligation')->name('hnr.waive');
    Route::post('/hnr/accounts/{user:public_slug}/reset', [HnrSettingsController::class, 'reset'])->name('hnr.reset');
    Route::get('/settings', [SiteSettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SiteSettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/uploads', [UploadLimitsController::class, 'edit'])->name('upload-settings.edit');
    Route::patch('/settings/uploads', [UploadLimitsController::class, 'update'])->name('upload-settings.update');
    Route::get('/categories', [CategorySettingsController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategorySettingsController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategorySettingsController::class, 'update'])->name('categories.update');
    Route::get('/permissions', [PermissionsController::class, 'index'])->name('permissions.index');
    Route::patch('/permissions/roles', [PermissionsController::class, 'updateRole'])->name('permissions.roles.update');
    Route::patch('/registration', [RegistrationSettingsController::class, 'update'])->name('registration.update');
    Route::post('/permissions/users', [PermissionsController::class, 'updateUser'])->name('permissions.users.update');
    Route::get('/ratio-credits', [RatioCreditController::class, 'index'])->name('ratio-credits.index');
    Route::patch('/ratio-credits/defaults', [RatioCreditController::class, 'updateDefaults'])->name('ratio-credits.defaults.update');
    Route::post('/ratio-credits/users/{user}', [RatioCreditController::class, 'adjust'])
        ->whereNumber('user')->name('ratio-credits.users.adjust');
    Route::view('/operations/discovery', 'sysop.operations.discovery')->name('operations.discovery');
    Route::get('/operations', OperationsDashboardController::class)->name('operations.index');
    Route::post('/operations/alerts/action', OperationsAlertActionController::class)->name('operations.alerts.action');
    Route::get('/operations/support-bundle', OperationsSupportBundleController::class)->name('operations.support-bundle');
    Route::get('/logs', LogCenterController::class)->name('logs.index');
    Route::post('/operations/runtime-jobs/toggle', RuntimeJobToggleController::class)->name('operations.runtime-jobs.toggle');
});
Route::middleware(['auth', 'staff', 'can:isAdmin', $adminThrottle])->group(function (): void {
    Route::get('/admin/users', [UserNameController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}/name', [UserNameController::class, 'update'])
        ->middleware('throttle:10,1')->name('admin.users.name.update');
    Route::patch('/admin/users/{user}/role', [UserRoleController::class, 'update'])
        ->name('admin.users.role.update');
});

/*
|--------------------------------------------------------------------------
| STAFF TORRENT MODERATION
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff', $moderationThrottle])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/alpha-feedback', [StaffAlphaFeedbackController::class, 'index'])
        ->name('alpha-feedback.index');
    Route::get('/alpha-feedback/{alphaFeedback}', [StaffAlphaFeedbackController::class, 'show'])
        ->whereNumber('alphaFeedback')
        ->name('alpha-feedback.show');
    Route::patch('/alpha-feedback/{alphaFeedback}', [StaffAlphaFeedbackController::class, 'update'])
        ->whereNumber('alphaFeedback')
        ->name('alpha-feedback.update');

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
Route::middleware(['auth', 'permission:forum.access', 'throttle:60,1'])->group(function (): void {
    Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
    Route::get('/forum/new-topic', [TopicController::class, 'create'])->name('topics.create');
    Route::get('/topics/{topic:slug}', [TopicController::class, 'show'])->name('topics.show');

    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/topics/{topic}/posts', [PostController::class, 'store'])->name('topics.posts.store');
});

Route::middleware(['auth', 'permission:forum.access', 'throttle:60,1'])->group(function (): void {
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

    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/restore', [PostController::class, 'restore'])->withTrashed()->name('posts.restore');
});

/*
|--------------------------------------------------------------------------
| PRIVATE MESSAGES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:messages.access'])->group(function (): void {
    Route::get('/pm', [PrivateMessageController::class, 'index'])->name('pm.index');
    Route::get('/pm/create', [PrivateMessageController::class, 'create'])->name('pm.create');
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

    Route::get('/torrents/upload', [TorrentUploadController::class, 'create'])->middleware('permission:torrent.upload')->name('torrents.upload');
    Route::post('/torrents/upload/analyse', [TorrentUploadController::class, 'analyse'])
        ->middleware(['permission:torrent.upload', 'throttle:30,1'])
        ->name('torrents.upload.analyse');

    Route::get('/torrents/{torrent}', [TorrentController::class, 'show'])
        ->middleware($torrentDetailsThrottle)
        ->name('torrents.show');

    Route::get('/torrents/{torrent}/download', [TorrentDownloadController::class, 'download'])
        ->middleware($torrentDownloadThrottle)
        ->name('torrents.download');

});

/*
|--------------------------------------------------------------------------
| TORRENT UPLOAD + ACCOUNT
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function (): void {
    Route::get('/alpha/feedback', [AlphaFeedbackController::class, 'create'])->name('alpha.feedback.create');
    Route::post('/alpha/feedback', [AlphaFeedbackController::class, 'store'])->name('alpha.feedback.store');

    Route::post('/torrents', [TorrentUploadController::class, 'store'])
        ->middleware(['permission:torrent.upload', 'throttle:torrent-upload'])
        ->name('torrents.store');
    Route::post('/torrents/{torrent}/follow', [TorrentFollowController::class, 'storeFromTorrent'])->name('torrents.follow.store');
    Route::get('/my/uploads', [MyUploadsController::class, 'index'])
        ->middleware('permission:torrent.upload')->name('my.uploads');
    Route::redirect('/my/discovery', '/my/follows')->name('my.discovery');
    Route::get('/my/follows', [TorrentFollowController::class, 'index'])->name('my.follows');
    Route::post('/my/follows', [TorrentFollowController::class, 'store'])->name('my.follows.store');
    Route::get('/my/watch-center', WatchCenterController::class)->name('my.watch-center');
    Route::get('/my/saved-views', [SavedIntentController::class, 'index'])->name('account.saved-intents.index');
    Route::post('/my/saved-views', [SavedIntentController::class, 'store'])->name('account.saved-intents.store');
    Route::get('/my/saved-views/{savedIntent}/apply', [SavedIntentController::class, 'apply'])
        ->whereNumber('savedIntent')
        ->name('account.saved-intents.apply');
    Route::delete('/my/saved-views/{savedIntent}', [SavedIntentController::class, 'destroy'])
        ->whereNumber('savedIntent')
        ->name('account.saved-intents.destroy');

    Route::get('/account/hnr', [HnrController::class, 'index'])->name('account.hnr');
    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/settings', [AccountController::class, 'edit'])->name('account.settings.edit');
    Route::patch('/account/settings', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.settings.update');
    Route::get('/account/access', [AccountController::class, 'access'])->name('account.access');
    Route::post('/account/passkey/rotate', [AccountController::class, 'rotatePasskey'])->middleware('throttle:5,1')->name('account.passkey.rotate');
    Route::get('/account/people', [AccountPeopleController::class, 'index'])->name('account.people');
    Route::post('/account/people/{user:public_slug}', [AccountPeopleController::class, 'store'])->middleware('throttle:30,1')->name('account.people.store');
    Route::delete('/account/people/{user:public_slug}', [AccountPeopleController::class, 'destroy'])->middleware('throttle:30,1')->name('account.people.destroy');

    Route::get('/account/snatches', [AccountSnatchController::class, 'index'])->name('account.snatches');
    Route::get('/account/invites', [AccountInviteController::class, 'index'])->name('account.invites');
    Route::post('/account/invites', [AccountInviteController::class, 'store'])
        ->middleware(['permission:invites.create', 'throttle:5,1440'])->name('account.invites.store');
    Route::get('/account/notifications', [AccountNotificationController::class, 'index'])->name('account.notifications.index');
    Route::post('/account/notifications/read-all', [AccountNotificationController::class, 'markAllRead'])
        ->name('account.notifications.read_all');
    Route::post('/account/notifications/{notification}/read', [AccountNotificationController::class, 'markRead'])
        ->whereNumber('notification')
        ->name('account.notifications.read');

    Route::get('/account/watch-presets', [NotificationWatchPresetController::class, 'index'])->name('account.watch-presets.index');
    Route::get('/account/watch-presets/create', [NotificationWatchPresetController::class, 'create'])->name('account.watch-presets.create');
    Route::post('/account/watch-presets', [NotificationWatchPresetController::class, 'store'])->name('account.watch-presets.store');
    Route::get('/account/watch-presets/{preset}/edit', [NotificationWatchPresetController::class, 'edit'])
        ->whereNumber('preset')
        ->name('account.watch-presets.edit');
    Route::patch('/account/watch-presets/{preset}', [NotificationWatchPresetController::class, 'update'])
        ->whereNumber('preset')
        ->name('account.watch-presets.update');
    Route::delete('/account/watch-presets/{preset}', [NotificationWatchPresetController::class, 'destroy'])
        ->whereNumber('preset')
        ->name('account.watch-presets.destroy');

    Route::get('/account/rss', [AccountRssController::class, 'index'])->name('account.rss.index');
    Route::post('/account/rss/enable', [AccountRssController::class, 'enable'])->name('account.rss.enable');
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

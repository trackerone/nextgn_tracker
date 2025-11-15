<?php

declare(strict_types=1);

use App\Http\Controllers\AccountInviteController;
use App\Http\Controllers\AccountSnatchController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\InviteAdminController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\TorrentModerationController;
use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\ScrapeController;
use Illuminate\Support\Facades\Route;

$adminThrottle = sprintf('throttle:%s', config('security.rate_limits.admin', '30,1'));

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::view('/', 'welcome');

Route::get('/health', HealthCheckController::class)->name('health.index');

Route::middleware(['auth', 'verified', 'role.min:10', $adminThrottle])
    ->get('/admin', static fn () => response()->json(['message' => 'Admin area']))
    ->name('demo.admin');

Route::middleware(['auth', 'verified', 'role.min:8'])
    ->get('/mod', static fn () => response()->json(['message' => 'Moderator area']))
    ->name('demo.mod');

Route::middleware(['auth', 'verified', 'role.min:8'])->group(function (): void {
    Route::get('/admin/invites', [InviteAdminController::class, 'index'])
        ->name('admin.invites.index');
    Route::post('/admin/invites', [InviteAdminController::class, 'store'])
        ->name('admin.invites.store');
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

Route::middleware(['auth', 'staff', 'can:isAdmin', $adminThrottle])->group(function (): void {
    Route::patch('/admin/users/{user}/role', [UserRoleController::class, 'update'])
        ->name('admin.users.role.update');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/torrents/moderation', [TorrentModerationController::class, 'index'])
        ->name('torrents.moderation.index');
    Route::post('/torrents/{torrent}/approve', [TorrentModerationController::class, 'approve'])
        ->name('torrents.approve');
    Route::post('/torrents/{torrent}/reject', [TorrentModerationController::class, 'reject'])
        ->name('torrents.reject');
    Route::post('/torrents/{torrent}/soft-delete', [TorrentModerationController::class, 'softDelete'])
        ->name('torrents.soft_delete');
});

Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
Route::get('/topics/{topic:slug}', [TopicController::class, 'show'])->name('topics.show');

Route::middleware(['auth', 'verified', 'role.min:1', 'throttle:60,1'])->group(function (): void {
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/topics/{topic}/posts', [PostController::class, 'store'])->name('topics.posts.store');
});

Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function (): void {
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
    Route::post('/posts/{post}/restore', [PostController::class, 'restore'])
        ->withTrashed()
        ->name('posts.restore');
});

Route::middleware(['auth', 'verified', 'role.min:1'])->group(function (): void {
    Route::get('/pm', [PrivateMessageController::class, 'index'])->name('pm.index');
    Route::get('/pm/{conversation}', [PrivateMessageController::class, 'show'])->name('pm.show');
    Route::post('/pm', [PrivateMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pm.store');
    Route::post('/pm/{conversation}/messages', [ConversationMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pm.messages.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/torrents', [TorrentController::class, 'index'])->name('torrents.index');
    Route::get('/torrents/{torrent}', [TorrentController::class, 'show'])
        ->whereNumber('torrent')
        ->name('torrents.show');
    Route::get('/torrents/{torrent}/download', [TorrentDownloadController::class, 'download'])
        ->whereNumber('torrent')
        ->name('torrents.download');
    Route::get('/torrents/{torrent}/magnet', [TorrentDownloadController::class, 'magnet'])
        ->whereNumber('torrent')
        ->name('torrents.magnet');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/torrents/upload', [TorrentUploadController::class, 'create'])->name('torrents.upload');
    Route::post('/torrents', [TorrentUploadController::class, 'store'])->name('torrents.store');
    Route::get('/account/snatches', [AccountSnatchController::class, 'index'])->name('account.snatches');
    Route::get('/account/invites', [AccountInviteController::class, 'index'])->name('account.invites');
});

Route::middleware(['throttle:120,1', 'tracker.validate-announce'])
    ->get('/announce/{passkey}', AnnounceController::class)
    ->name('announce');

Route::middleware(['throttle:120,1'])
    ->get('/scrape', ScrapeController::class)
    ->name('scrape');

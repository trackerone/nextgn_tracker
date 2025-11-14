<?php

declare(strict_types=1);

use App\Http\Controllers\AccountSnatchController;
use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\ScrapeController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/health', HealthCheckController::class)->name('health.index');

Route::middleware(['auth', 'verified', 'role.min:10'])
    ->get('/admin', static fn () => response()->json(['message' => 'Admin area']))
    ->name('demo.admin');

Route::middleware(['auth', 'verified', 'role.min:8'])
    ->get('/mod', static fn () => response()->json(['message' => 'Moderator area']))
    ->name('demo.mod');

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

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/torrents', [TorrentController::class, 'index'])->name('torrents.index');
    Route::get('/torrents/{slug}', [TorrentController::class, 'show'])->name('torrents.show');
    Route::get('/account/snatches', [AccountSnatchController::class, 'index'])->name('account.snatches');
});

Route::middleware(['throttle:120,1'])
    ->get('/announce/{passkey}', AnnounceController::class)
    ->name('announce');

Route::middleware(['throttle:120,1'])
    ->get('/scrape', ScrapeController::class)
    ->name('scrape');

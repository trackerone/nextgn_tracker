<<<<<< codex/harden-file-upload-surface-in-nextgn-tracker
<?php

declare(strict_types=1);

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
=======
<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'api.hmac'])->group(static function (): void {});
>>>>>> main

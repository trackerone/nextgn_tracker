<?php

declare(strict_types=1);

namespace App\Services\Torrents;

use App\Models\User;

interface UploadPreflightContextBuilderContract
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function forUser(User $user, array $input = []): UploadPreflightContext;

    /**
     * @param  array<string, mixed>  $input
     */
    public function forPayload(User $user, string $torrentPayload, array $input = []): UploadPreflightContext;
}

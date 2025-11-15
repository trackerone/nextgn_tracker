<?php

declare(strict_types=1);

namespace App\Services\Tracker;

use Illuminate\Http\Response;

class FailureResponder
{
    private const REASONS = [
        'unauthorized_client' => 'Unauthorized client',
        'invalid_announce' => 'Invalid announce',
        'rate_limit' => 'Rate limit exceeded',
        'client_banned' => 'Client banned',
        'invalid_passkey' => 'Invalid passkey',
        'invalid_parameters' => 'Invalid parameters',
    ];

    public function fail(string $reason): Response
    {
        $message = self::REASONS[$reason] ?? self::REASONS['invalid_announce'];
        $payload = sprintf('d14:failure reason%d:%se', strlen($message), $message);

        return response($payload, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}

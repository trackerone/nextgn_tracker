<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RottenTomatoesRatingsController extends Controller
{
    public function __invoke(Request $request, SiteSettingsRepository $settings, string $imdbId): JsonResponse
    {
        $input = $request->validate([
            'enabled' => ['required', 'boolean'],
            'slug' => ['required_if:enabled,true', 'nullable', 'string', 'regex:/^[a-z0-9_-]{1,150}$/D'],
            'critics' => ['nullable', 'integer', 'between:0,100'],
            'audience' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $enabled = (bool) $input['enabled'];
        if ($enabled && (! isset($input['slug']) || (! isset($input['critics']) && ! isset($input['audience'])))) {
            throw ValidationException::withMessages(['critics' => 'A film slug and at least one score are required.']);
        }

        $settings->set('metadata.rotten_tomatoes.'.$imdbId, [
            'enabled' => $enabled,
            'slug' => $input['slug'] ?? null,
            'critics' => isset($input['critics']) ? (int) $input['critics'] : null,
            'audience' => isset($input['audience']) ? (int) $input['audience'] : null,
        ], 'json');

        return response()->json(['enabled' => $enabled]);
    }
}

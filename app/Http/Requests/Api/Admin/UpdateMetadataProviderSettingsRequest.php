<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMetadataProviderSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'enrichment_enabled' => ['required', 'boolean'],
            'auto_on_publish' => ['required', 'boolean'],
            'refresh_after_days' => ['required', 'integer', 'min:1'],
            'providers' => ['required', 'array'],
            'providers.tmdb' => ['required', 'array'],
            'providers.trakt' => ['required', 'array'],
            'providers.imdb' => ['required', 'array'],
            'providers.tmdb.enabled' => ['required', 'boolean'],
            'providers.trakt.enabled' => ['required', 'boolean'],
            'providers.imdb.enabled' => ['required', 'boolean'],
            'priority' => ['required', 'array', 'min:1'],
            'priority.*' => ['required', 'string', Rule::in(['tmdb', 'trakt', 'imdb'])],
        ];
    }
}

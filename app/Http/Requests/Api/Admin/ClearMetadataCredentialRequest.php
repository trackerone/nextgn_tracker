<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class ClearMetadataCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function passedValidation(): void
    {
        $provider = (string) $this->route('provider');
        $field = (string) $this->route('field');

        $valid = match ($provider) {
            'tmdb' => in_array($field, ['api_key'], true),
            'trakt' => in_array($field, ['client_id', 'client_secret'], true),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['field' => 'Unknown provider/field combination.']);
        }
    }
}

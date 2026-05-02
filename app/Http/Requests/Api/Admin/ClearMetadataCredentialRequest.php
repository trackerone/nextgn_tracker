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

        $allowedFields = match ($provider) {
            'tmdb' => ['api_key'],
            'trakt' => ['client_id', 'client_secret'],
            default => null,
        };

        if ($allowedFields === null) {
            throw ValidationException::withMessages(['provider' => 'Unknown provider.']);
        }

        if (! in_array($field, $allowedFields, true)) {
            throw ValidationException::withMessages(['field' => 'Field is not valid for provider.']);
        }
    }
}

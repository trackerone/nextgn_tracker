<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class SetMetadataCredentialRequest extends FormRequest
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
        return [
            'api_key' => ['sometimes', 'string', 'min:1'],
            'client_id' => ['sometimes', 'string', 'min:1'],
            'client_secret' => ['sometimes', 'string', 'min:1'],
        ];
    }

    protected function passedValidation(): void
    {
        $provider = (string) $this->route('provider');
        $allowedFields = match ($provider) {
            'tmdb' => ['api_key'],
            'trakt' => ['client_id', 'client_secret'],
            default => null,
        };

        if ($allowedFields === null) {
            throw ValidationException::withMessages(['provider' => 'Unknown provider.']);
        }

        $providedFields = array_keys($this->validated());

        if ($providedFields === []) {
            throw ValidationException::withMessages(['credentials' => 'At least one credential field is required.']);
        }

        foreach ($providedFields as $field) {
            if (! in_array($field, $allowedFields, true)) {
                throw ValidationException::withMessages([$field => 'Field is not valid for provider.']);
            }
        }
    }
}

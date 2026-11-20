<?php

declare(strict_types=1);

namespace App\Http\Requests\PrivateMessages;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->expectsJson() && is_string($this->input('recipient_slug'))) {
            $this->merge(['recipient_slug' => mb_strtolower(trim($this->string('recipient_slug')->value()))]);
        }
    }

    public function messages(): array
    {
        return ['recipient_slug.exists' => 'Member not found. Check their profile address or choose Send message on their profile.'];
    }

    public function rules(): array
    {
        if (! $this->expectsJson()) {
            return [
                'recipient_slug' => ['required', 'string', 'exists:users,public_slug'],
                'body_md' => ['required', 'string', 'min:3'],
            ];
        }

        return [
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body_md' => ['required', 'string', 'min:3'],
        ];
    }
}

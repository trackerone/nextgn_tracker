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

    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body_md' => ['required', 'string', 'min:3'],
        ];
    }
}

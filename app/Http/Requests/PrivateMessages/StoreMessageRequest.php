<?php

declare(strict_types=1);

namespace App\Http\Requests\PrivateMessages;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body_md' => ['required', 'string', 'min:3'],
        ];
    }
}

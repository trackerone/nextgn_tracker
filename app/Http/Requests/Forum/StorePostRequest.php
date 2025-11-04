<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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

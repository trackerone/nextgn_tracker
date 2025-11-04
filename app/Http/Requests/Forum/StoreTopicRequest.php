<?php

declare(strict_types=1);

namespace App\Http\Requests\Forum;

use Illuminate\Foundation\Http\FormRequest;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:140'],
            'body_md' => ['required', 'string', 'min:3'],
        ];
    }
}

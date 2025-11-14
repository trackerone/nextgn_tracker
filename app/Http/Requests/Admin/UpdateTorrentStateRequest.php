<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTorrentStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_approved' => ['required', 'boolean'],
            'is_banned' => ['required', 'boolean'],
            'freeleech' => ['required', 'boolean'],
            'ban_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_approved' => $this->boolean('is_approved'),
            'is_banned' => $this->boolean('is_banned'),
            'freeleech' => $this->boolean('freeleech'),
        ]);
    }
}

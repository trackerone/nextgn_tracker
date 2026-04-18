<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTorrentFollowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->string('title')->value()) : $this->input('title'),
            'type' => is_string($this->input('type')) ? strtolower(trim($this->string('type')->value())) : $this->input('type'),
            'resolution' => is_string($this->input('resolution')) ? trim($this->string('resolution')->value()) : $this->input('resolution'),
            'source' => is_string($this->input('source')) ? trim($this->string('source')->value()) : $this->input('source'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['movie', 'tv'])],
            'resolution' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:64'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
        ];
    }
}


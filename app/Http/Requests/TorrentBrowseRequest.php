<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TorrentBrowseRequest extends FormRequest
{
    private const ORDER_OPTIONS = ['created', 'size', 'seeders', 'leechers', 'completed'];
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPerPage = (int) config('search.max_per_page', 100);

        return [
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'order' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

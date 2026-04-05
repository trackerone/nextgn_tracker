<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Torrents\TorrentBrowseFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BrowseTorrentsRequest extends FormRequest
{
    private const ORDER_OPTIONS = [
        'id',
        'name',
        'created',
        'uploaded',
        'uploaded_at',
        'size',
        'size_bytes',
        'seeders',
        'leechers',
        'completed',
    ];

    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $category = $this->input('category');
        $categoryId = $this->input('category_id', $category);

        $this->merge([
            'q' => is_string($this->input('q')) ? trim($this->string('q')->value()) : $this->input('q'),
            'type' => is_string($this->input('type')) ? trim($this->string('type')->value()) : $this->input('type'),
            'direction' => is_string($this->input('direction'))
                ? strtolower(trim($this->string('direction')->value()))
                : $this->input('direction'),
            'sort' => is_string($this->input('sort')) ? trim($this->string('sort')->value()) : $this->input('sort'),
            'order' => is_string($this->input('order')) ? trim($this->string('order')->value()) : $this->input('order'),
            'category_id' => $categoryId,
        ]);
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
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'order' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filters(): TorrentBrowseFilters
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TorrentBrowseFilters::fromInput($validated);
    }

    public function perPage(int $default = 25): int
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        $perPage = $validated['per_page'] ?? $default;

        return (int) $perPage;
    }
}

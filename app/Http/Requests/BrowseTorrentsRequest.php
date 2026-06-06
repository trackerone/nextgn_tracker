<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Torrents\TorrentBrowseFilters;
use App\Support\Torrents\TorrentSearchExpression;
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
        $searchExpression = TorrentSearchExpression::fromQuery(
            is_string($this->input('q')) ? trim($this->string('q')->value()) : ''
        );

        $this->merge([
            'q' => $searchExpression->text,
            'type' => is_string($this->input('type')) ? trim($this->string('type')->value()) : $this->input('type'),
            'release_group' => $this->stringOrNull('release_group') ?? $searchExpression->releaseGroup,
            'language' => $this->stringOrNull('language') ?? $searchExpression->language,
            'audio_language' => $this->stringOrNull('audio_language') ?? $searchExpression->audioLanguage,
            'subtitle_language' => $this->stringOrNull('subtitle_language') ?? $searchExpression->subtitleLanguage,
            'resolution' => $this->stringOrNull('resolution') ?? $searchExpression->resolution,
            'source' => $this->stringOrNull('source') ?? $searchExpression->source,
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
            'release_group' => ['nullable', 'string', 'max:80'],
            'language' => ['nullable', 'string', 'max:80'],
            'audio_language' => ['nullable', 'string', 'max:80'],
            'subtitle_language' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'order' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPerPage],
            'page' => ['nullable', 'integer', 'min:1'],
            'grouped' => ['nullable', 'boolean'],
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

    public function grouped(bool $default = true): bool
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        $grouped = $validated['grouped'] ?? $default;

        if (is_bool($grouped)) {
            return $grouped;
        }

        return filter_var($grouped, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}

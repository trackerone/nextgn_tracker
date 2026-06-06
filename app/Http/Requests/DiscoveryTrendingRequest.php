<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DiscoveryTrendingRequest extends FormRequest
{
    private const DEFAULT_WINDOW = '30d';

    /**
     * @var array<string, int>
     */
    private const WINDOW_DAYS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * @var array<int, string>
     */
    private const CATEGORIES = [
        'sources',
        'resolutions',
        'release_groups',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $window = $this->input('window');
        $category = $this->input('category');

        $this->merge([
            'window' => is_string($window) ? trim($window) : $window,
            'category' => is_string($category) ? trim($category) : $category,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'window' => ['nullable', Rule::in(array_keys(self::WINDOW_DAYS))],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ];
    }

    public function windowDays(): int
    {
        /** @var array{window?: string} $validated */
        $validated = $this->validated();

        $window = $validated['window'] ?? self::DEFAULT_WINDOW;

        return self::WINDOW_DAYS[$window];
    }

    public function category(): ?string
    {
        /** @var array{category?: string|null} $validated */
        $validated = $this->validated();

        return $validated['category'] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DiscoveryPopularRequest extends FormRequest
{
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
        $category = $this->input('category');

        $this->merge([
            'category' => is_string($category) ? trim($category) : $category,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ];
    }

    public function category(): ?string
    {
        /** @var array{category?: string|null} $validated */
        $validated = $this->validated();

        return $validated['category'] ?? null;
    }
}

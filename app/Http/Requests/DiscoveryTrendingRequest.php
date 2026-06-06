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

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $window = $this->input('window');

        $this->merge([
            'window' => is_string($window) ? trim($window) : $window,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'window' => ['nullable', Rule::in(array_keys(self::WINDOW_DAYS))],
        ];
    }

    public function windowDays(): int
    {
        /** @var array{window?: string} $validated */
        $validated = $this->validated();

        $window = $validated['window'] ?? self::DEFAULT_WINDOW;

        return self::WINDOW_DAYS[$window];
    }
}

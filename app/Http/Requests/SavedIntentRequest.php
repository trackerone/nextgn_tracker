<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SavedIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SavedIntentRequest extends FormRequest
{
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

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

    private const STRING_FILTERS = [
        'q',
        'title',
        'type',
        'resolution',
        'source',
        'release_group',
        'imdb_id',
        'tmdb_id',
        'language',
        'audio_language',
        'subtitle_language',
        'subtitles',
        'order',
        'direction',
        'grouped',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (array_merge(['name'], self::STRING_FILTERS) as $key) {
            if (is_string($this->input($key))) {
                $normalized[$key] = trim($this->string($key)->value());
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()?->id;
        $intent = $this->route('savedIntent');
        $intentId = $intent instanceof SavedIntent ? $intent->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('saved_intents', 'name')->where('user_id', $userId)->ignore($intentId),
            ],
            'q' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'resolution' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:32'],
            'release_group' => ['nullable', 'string', 'max:80'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'tmdb_id' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:32'],
            'audio_language' => ['nullable', 'string', 'max:32'],
            'subtitle_language' => ['nullable', 'string', 'max:32'],
            'subtitles' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'order' => ['nullable', Rule::in(self::ORDER_OPTIONS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'grouped' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, criteria: array<string, mixed>}
     */
    public function intentPayload(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $name = (string) $validated['name'];
        unset($validated['name']);

        return [
            'name' => $name,
            'criteria' => $this->criteriaFrom($validated),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function criteriaFrom(array $validated): array
    {
        $criteria = [];

        foreach ($validated as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'grouped') {
                $criteria[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

                continue;
            }

            $criteria[$key] = $value;
        }

        return $criteria;
    }
}

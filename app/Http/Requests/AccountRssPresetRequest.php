<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\RssFeedPreset;
use App\Services\Rss\RssFeedFilterNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AccountRssPresetRequest extends FormRequest
{
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    private const LANGUAGE_PATTERN = '/^[A-Za-z][A-Za-z\s,-]*$/';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'q', 'type', 'resolution', 'source', 'release_group', 'language', 'audio_language', 'subtitle_language', 'subtitles'] as $key) {
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
        $preset = $this->route('preset');
        $presetId = $preset instanceof RssFeedPreset ? $preset->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('rss_feed_presets', 'name')->where('user_id', $userId)->ignore($presetId),
            ],
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'resolution' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:32'],
            'release_group' => ['nullable', 'string', 'max:80'],
            'language' => ['nullable', 'string', 'max:32', 'regex:'.self::LANGUAGE_PATTERN],
            'audio_language' => ['nullable', 'string', 'max:32', 'regex:'.self::LANGUAGE_PATTERN],
            'subtitle_language' => ['nullable', 'string', 'max:32', 'regex:'.self::LANGUAGE_PATTERN],
            'subtitles' => ['nullable', 'string', 'max:255', 'regex:'.self::LANGUAGE_PATTERN],
            'freeleech' => ['nullable', 'boolean'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array{name: string, filters: array<string, mixed>}
     */
    public function presetPayload(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $name = (string) $validated['name'];
        unset($validated['name']);

        return [
            'name' => $name,
            'filters' => app(RssFeedFilterNormalizer::class)->normalizedForStorage($validated),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RssFeedRequest extends FormRequest
{
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    private const LANGUAGE_PATTERN = '/^[A-Za-z][A-Za-z\s,-]*$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => is_string($this->input('q')) ? trim($this->string('q')->value()) : $this->input('q'),
            'type' => is_string($this->input('type')) ? trim($this->string('type')->value()) : $this->input('type'),
            'resolution' => is_string($this->input('resolution')) ? trim($this->string('resolution')->value()) : $this->input('resolution'),
            'source' => is_string($this->input('source')) ? trim($this->string('source')->value()) : $this->input('source'),
            'release_group' => is_string($this->input('release_group')) ? trim($this->string('release_group')->value()) : $this->input('release_group'),
            'language' => is_string($this->input('language')) ? trim($this->string('language')->value()) : $this->input('language'),
            'audio_language' => is_string($this->input('audio_language')) ? trim($this->string('audio_language')->value()) : $this->input('audio_language'),
            'subtitle_language' => is_string($this->input('subtitle_language')) ? trim($this->string('subtitle_language')->value()) : $this->input('subtitle_language'),
            'subtitles' => is_string($this->input('subtitles')) ? trim($this->string('subtitles')->value()) : $this->input('subtitles'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
     * @return array{q: string, type: string, resolution: string, source: string, release_group: string, language: string, audio_language: string, subtitle_language: string, subtitles: string, freeleech: bool|null, category: int|null, limit: int}
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        $freeleech = $validated['freeleech'] ?? null;

        return [
            'q' => (string) ($validated['q'] ?? ''),
            'type' => (string) ($validated['type'] ?? ''),
            'resolution' => (string) ($validated['resolution'] ?? ''),
            'source' => (string) ($validated['source'] ?? ''),
            'release_group' => (string) ($validated['release_group'] ?? ''),
            'language' => (string) ($validated['language'] ?? ''),
            'audio_language' => (string) ($validated['audio_language'] ?? ''),
            'subtitle_language' => (string) ($validated['subtitle_language'] ?? ''),
            'subtitles' => (string) ($validated['subtitles'] ?? ''),
            'freeleech' => $freeleech === null ? null : filter_var($freeleech, FILTER_VALIDATE_BOOLEAN),
            'category' => isset($validated['category']) ? (int) $validated['category'] : null,
            'limit' => min(100, max(1, (int) ($validated['limit'] ?? 50))),
        ];
    }
}

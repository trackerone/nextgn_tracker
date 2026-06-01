<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Http\Requests\StoreTorrentRequest;
use Closure;

final class TorrentUploadStoreRequest extends StoreTorrentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $safeLanguageMetadata = [
            'nullable',
            'string',
            'max:255',
            static function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (!is_string($value)) {
                    return;
                }

                if (str_contains($value, '<') || str_contains($value, '>')) {
                    $fail('The '.$attribute.' field contains invalid characters.');

                    return;
                }

                if (preg_match('#^[A-Za-z0-9 .,_/;-]+$#', $value) !== 1) {
                    $fail('The '.$attribute.' field contains invalid characters.');
                }
            },
        ];

        foreach (['language', 'audio_language', 'subtitle_language', 'subtitles'] as $field) {
            $rules[$field] = $safeLanguageMetadata;
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $tagsInput = $this->input('tags_input');

        if (is_string($tagsInput)) {
            $tags = array_values(array_filter(
                array_map(static fn (string $tag): string => trim($tag), explode(',', $tagsInput)),
                static fn (string $tag): bool => $tag !== ''
            ));

            $this->merge(['tags' => $tags]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Http\Requests\StoreTorrentRequest;
use Illuminate\Validation\Validator as LaravelValidator;

final class TorrentUploadStoreRequest extends StoreTorrentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules();
    }

    public function withValidator(LaravelValidator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (LaravelValidator $validator): void {
            foreach (['language', 'audio_language', 'subtitle_language', 'subtitles'] as $field) {
                $value = $this->input($field);

                if (! is_string($value)) {
                    continue;
                }

                if ((str_contains($value, '<') || str_contains($value, '>')) && ! $validator->errors()->has($field)) {
                    $validator->errors()->add($field, 'The '.$field.' field contains invalid characters.');
                }
            }
        });
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

<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\SecurityAuditLog;
use App\Support\Uploads\TorrentUploadRules;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator as LaravelValidator;

trait ValidatesTorrentUpload
{
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedOptionalMetadataInput());
    }

    /**
     * @return array<string, int|string|null>
     */
    private function normalizedOptionalMetadataInput(): array
    {
        $fields = [
            'title',
            'year',
            'release_group',
            'imdb_id',
            'tmdb_id',
            'language',
            'audio_language',
            'subtitle_language',
            'subtitles',
        ];

        $normalized = [];

        $input = $this->all();

        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if (in_array($field, self::languageMetadataFields(), true)) {
                if (str_contains($value, '<') || str_contains($value, '>')) {
                    $normalized[$field] = $value;
                    continue;
                }

                $value = preg_replace('/\s*([,;\/|])\s*/', '$1', $value) ?? $value;
            }

            if ($value === '') {
                $normalized[$field] = null;
                continue;
            }

            $normalized[$field] = $field === 'year' && ctype_digit($value) ? (int) $value : $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return TorrentUploadRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TorrentUploadRules::messages();
    }

    public function withValidator(LaravelValidator $validator): void
    {
        $validator->after(function (LaravelValidator $validator): void {
            foreach (self::languageMetadataFields() as $field) {
                $value = $this->input($field);

                if (!is_string($value)) {
                    continue;
                }

                if (
                    (str_contains($value, '<') || str_contains($value, '>'))
                    && !$validator->errors()->has($field)
                ) {
                    $validator->errors()->add($field, 'The '.$field.' field contains invalid characters.');
                }
            }

            if ($this->file('nfo_file') !== null && $this->filled('nfo_text')) {
                $validator->errors()->add('nfo_text', 'Provide either an NFO file or inline text, not both.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        SecurityAuditLog::log($this->user(), 'torrent.upload.validation_failed', [
            'errors' => $validator->errors()->keys(),
            'has_torrent_file' => $this->hasFile('torrent_file'),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * @return list<string>
     */
    private static function languageMetadataFields(): array
    {
        return [
            'language',
            'audio_language',
            'subtitle_language',
            'subtitles',
        ];
    }
}

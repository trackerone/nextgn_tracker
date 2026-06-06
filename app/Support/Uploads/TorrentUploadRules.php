<?php

declare(strict_types=1);

namespace App\Support\Uploads;

final class TorrentUploadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $torrentMimeRule = 'mimetypes:'.implode(',', self::configList('upload.torrents.allowed_mimes'));
        $torrentExtensionRule = 'extensions:'.implode(',', self::configList('upload.torrents.allowed_extensions'));
        $nfoMimeRule = 'mimetypes:'.implode(',', self::configList('upload.nfo.allowed_mimes'));
        $nfoExtensionRule = 'extensions:'.implode(',', self::configList('upload.nfo.allowed_extensions'));

        $safeMetadataText = [
            'nullable',
            'string',
            'max:255',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! is_string($value)) {
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', 'string', 'in:movie,tv,music,game,software,other'],
            'title' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:1900,'.(((int) date('Y')) + 2)],
            'release_group' => ['nullable', 'string', 'max:120'],
            'language' => $safeMetadataText,
            'audio_language' => $safeMetadataText,
            'subtitle_language' => $safeMetadataText,
            'subtitles' => $safeMetadataText,
            'description' => ['nullable', 'string'],
            'tags_input' => ['nullable', 'string', 'max:512'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'resolution' => ['nullable', 'string', 'max:20'],
            'codecs' => ['nullable', 'array'],
            'codecs.*' => ['nullable', 'string', 'max:50'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'tmdb_id' => ['nullable', 'string', 'max:32'],
            'torrent_file' => [
                'required',
                'file',
                $torrentMimeRule,
                $torrentExtensionRule,
                'max:'.((int) config('upload.torrents.max_kilobytes', 1024)),
            ],
            'nfo_file' => [
                'nullable',
                'file',
                $nfoMimeRule,
                $nfoExtensionRule,
                'max:'.((int) config('upload.nfo.max_kilobytes', 256)),
            ],
            'nfo_text' => ['nullable', 'string', 'max:'.((int) config('upload.nfo.max_characters', 262144))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'A name is required for the torrent.',
            'type.in' => 'The selected torrent type is invalid.',
            'torrent_file.required' => 'A .torrent file is required.',
            'torrent_file.mimetypes' => 'The uploaded file must be a valid .torrent file.',
            'torrent_file.extensions' => 'The uploaded file must use the .torrent extension.',
            'nfo_file.mimetypes' => 'The uploaded NFO must be a plain text file.',
            'nfo_file.extensions' => 'The uploaded NFO must use the .nfo or .txt extension.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function configList(string $key): array
    {
        $value = config($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}

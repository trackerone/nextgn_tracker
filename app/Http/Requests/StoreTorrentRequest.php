<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTorrentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'type' => ['required', 'string', 'max:32'],

            'torrent_file' => [
                'required',
                'file',
                sprintf('max:%d', (int) config('upload.torrents.max_kilobytes', 1024)),
                'mimetypes:application/x-bittorrent',
            ],

            'nfo_file' => [
                'nullable',
                'file',
                sprintf('max:%d', (int) config('upload.nfo.max_kilobytes', 256)),
                'mimetypes:text/plain',
            ],

            'nfo_text' => [
                'nullable',
                'string',
                sprintf('max:%d', (int) config('upload.nfo.max_characters', 262144)),
            ],

            'description' => ['nullable', 'string'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],

            'source' => ['nullable', 'string', 'max:64'],
            'resolution' => ['nullable', 'string', 'max:32'],

            'codecs' => ['nullable', 'array'],
            'codecs.*' => ['nullable', 'string', 'max:64'],

            'imdb_id' => ['nullable', 'string', 'max:32'],
            'tmdb_id' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A name is required for the torrent.',
            'torrent_file.required' => 'A .torrent file is required.',
            'torrent_file.mimetypes' => 'The uploaded file must be a valid .torrent file.',
        ];
    }
}

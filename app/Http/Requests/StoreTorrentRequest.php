<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTorrentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Upload kræver allerede auth-middleware på route-niveau,
        // så her kan vi bare returnere true.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $torrentConfig = config('upload.torrents');

        return [
            'name' => ['required', 'string', 'max:255'],

            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', 'string', 'max:50'],

            'source' => ['nullable', 'string', 'max:255'],
            'resolution' => ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],

            'codecs' => ['nullable', 'array'],
            'codecs.*' => ['nullable', 'string', 'max:255'],

            'imdb_id' => ['nullable', 'string', 'max:20'],
            'tmdb_id' => ['nullable', 'string', 'max:20'],

            'nfo_text' => ['nullable', 'string'],
            'nfo_storage_path' => ['nullable', 'string', 'max:1024'],

            'torrent_file' => [
                'required',
                'file',
                // Laravel "max" er i kilobytes:
                'max:' . ($torrentConfig['max_kilobytes'] ?? 1024),
                'mimetypes:' . implode(',', $torrentConfig['allowed_mimes'] ?? ['application/x-bittorrent']),
                'mimes:' . implode(',', $torrentConfig['allowed_extensions'] ?? ['torrent']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'torrent name',
            'torrent_file' => 'torrent file',
        ];
    }
}

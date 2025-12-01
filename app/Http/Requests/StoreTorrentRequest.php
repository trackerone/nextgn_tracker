<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTorrentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $config = config('upload.torrents');

        return [
            'torrent_file' => [
                'required',
                'file',
                'mimetypes:' . implode(',', $config['allowed_mimes']),
                'mimes:' . implode(',', $config['allowed_extensions']),
                'max:' . $config['max_kilobytes'],
            ],

            // optional fields
            'name'         => ['nullable', 'string', 'max:255'],
            'category_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'type'         => ['nullable', 'string', 'max:50'],
            'description'  => ['nullable', 'string'],
        ];
    }
}

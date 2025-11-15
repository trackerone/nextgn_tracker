<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TorrentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tagsInput = $this->input('tags_input');

        if (is_string($tagsInput)) {
            $tags = array_values(array_filter(array_map(static fn (string $tag): string => trim($tag), explode(',', $tagsInput)), static fn (string $tag): bool => $tag !== ''));
            $this->merge(['tags' => $tags]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['required', 'string', 'in:movie,tv,music,game,software,other'],
            'description' => ['nullable', 'string'],
            'tags_input' => ['nullable', 'string', 'max:512'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'source' => ['nullable', 'string', 'max:50'],
            'resolution' => ['nullable', 'string', 'max:20'],
            'codecs' => ['nullable', 'array'],
            'codecs.*' => ['nullable', 'string', 'max:50'],
            'torrent_file' => [
                'required',
                'file',
                'mimetypes:application/x-bittorrent,application/octet-stream',
                'max:'.config('security.max_torrent_kilobytes'),
            ],
            'nfo_file' => [
                'nullable',
                'file',
                'mimetypes:text/plain',
                'max:'.config('security.max_nfo_kilobytes'),
            ],
            'nfo_text' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->file('nfo_file') !== null && $this->filled('nfo_text')) {
                $validator->errors()->add('nfo_text', 'Provide either an NFO file or inline text, not both.');
            }
        });
    }
}

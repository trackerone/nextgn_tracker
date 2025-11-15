<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SecurityAuditLog;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as LaravelValidator;

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
        $torrentMimeRule = 'mimetypes:'.implode(',', config('upload.torrents.allowed_mimes'));
        $torrentExtensionRule = 'extensions:'.implode(',', config('upload.torrents.allowed_extensions'));
        $nfoMimeRule = 'mimetypes:'.implode(',', config('upload.nfo.allowed_mimes'));
        $nfoExtensionRule = 'extensions:'.implode(',', config('upload.nfo.allowed_extensions'));

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
                $torrentMimeRule,
                $torrentExtensionRule,
                'max:'.config('upload.torrents.max_kilobytes'),
            ],
            'nfo_file' => [
                'nullable',
                'file',
                $nfoMimeRule,
                $nfoExtensionRule,
                'max:'.config('upload.nfo.max_kilobytes'),
            ],
            'nfo_text' => ['nullable', 'string', 'max:'.config('upload.nfo.max_characters')],
        ];
    }

    public function withValidator(LaravelValidator $validator): void
    {
        $validator->after(function (LaravelValidator $validator): void {
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
}

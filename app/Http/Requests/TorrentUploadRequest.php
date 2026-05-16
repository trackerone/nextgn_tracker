<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTorrentUpload;
use Illuminate\Foundation\Http\FormRequest;

class TorrentUploadRequest extends FormRequest
{
    use ValidatesTorrentUpload;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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

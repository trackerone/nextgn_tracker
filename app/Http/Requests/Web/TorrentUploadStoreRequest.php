<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Http\Requests\StoreTorrentRequest;

final class TorrentUploadStoreRequest extends StoreTorrentRequest
{
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

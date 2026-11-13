<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Http\Requests\StoreTorrentRequest;

final class AnalyseTorrentUploadRequest extends StoreTorrentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_intersect_key(parent::rules(), array_flip(['torrent_file', 'nfo_file', 'nfo_text']));
    }
}

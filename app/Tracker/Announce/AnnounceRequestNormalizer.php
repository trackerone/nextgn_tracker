<?php

declare(strict_types=1);

namespace App\Tracker\Announce;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AnnounceRequestNormalizer
{
    public function normalize(Request $request): AnnounceRequestData|AnnounceResult
    {
        $validator = Validator::make($request->query(), [
            'info_hash' => 'required|string',
            'peer_id' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'uploaded' => 'required|integer|min:0',
            'downloaded' => 'required|integer|min:0',
            'left' => 'required|integer|min:0',
            'event' => 'sometimes|string|in:started,stopped,completed',
            'numwant' => 'sometimes|integer|min:1|max:200',
            'ip' => 'sometimes|ip',
        ]);

        if ($validator->fails()) {
            return AnnounceResult::failure((string) $validator->errors()->first());
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();

        $numwant = isset($data['numwant']) ? (int) $data['numwant'] : 50;

        return new AnnounceRequestData(
            infoHash: (string) $data['info_hash'],
            peerId: (string) $data['peer_id'],
            port: (int) $data['port'],
            uploaded: (int) $data['uploaded'],
            downloaded: (int) $data['downloaded'],
            left: (int) $data['left'],
            event: isset($data['event']) ? (string) $data['event'] : null,
            numwant: max(1, min($numwant, 200)),
            ip: isset($data['ip']) ? (string) $data['ip'] : null,
        );
    }
}

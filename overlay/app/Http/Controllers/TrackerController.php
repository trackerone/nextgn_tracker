<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Bencode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackerController extends Controller
{
    public function announce(Request $request): Response
    {
        if (Config::get('tracker.mode') === 'external') {
            $url = Config::get('tracker.external_announce');

            if (! is_string($url) || $url === '') {
                return response('tracker misconfigured', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (Str::startsWith($url, ['http://', 'https://'])) {
                return redirect()->away($url);
            }

            return $this->bencode([
                'failure reason' => 'use external tracker',
                'announce' => $url,
            ]);
        }

        $infoHash = $this->rawBin($request->query('info_hash', ''));
        $peerId = $this->rawBin($request->query('peer_id', ''));
        $port = (int) $request->query('port', 0);

        if (strlen($infoHash) !== 20 || strlen($peerId) !== 20 || $port <= 0) {
            return $this->bencode(['failure reason' => 'invalid parameters']);
        }

        $uploaded = (int) $request->query('uploaded', 0);
        $downloaded = (int) $request->query('downloaded', 0);
        $left = (int) $request->query('left', 0);
        $event = (string) $request->query('event', '');
        $compact = (int) $request->query('compact', 1);
        $ipParam = $request->query('ip');

        $ip = filter_var($ipParam, FILTER_VALIDATE_IP) ?: $request->ip();
        $now = now();

        $hexInfo = bin2hex($infoHash);
        $hexPeer = bin2hex($peerId);

        DB::table('peers')->upsert([
            'info_hash' => $hexInfo,
            'peer_id' => $hexPeer,
            'ip' => $ip,
            'port' => $port,
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
            'left_bytes' => $left,
            'last_announce' => $now,
            'event' => $event,
        ], ['info_hash', 'peer_id'], [
            'ip',
            'port',
            'uploaded',
            'downloaded',
            'left_bytes',
            'last_announce',
            'event',
        ]);

        DB::table('peers')
            ->where('last_announce', '<', $now->copy()->subMinutes(45))
            ->delete();

        if ($event === 'stopped') {
            DB::table('peers')
                ->where([
                    'info_hash' => $hexInfo,
                    'peer_id' => $hexPeer,
                ])
                ->delete();

            return $this->bencode([
                'interval' => 1800,
                'complete' => 0,
                'incomplete' => 0,
                'peers' => $compact ? '' : [],
            ]);
        }

        $rows = DB::table('peers')
            ->select('ip', 'port', 'peer_id', 'left_bytes')
            ->where('info_hash', $hexInfo)
            ->where('peer_id', '!=', $hexPeer)
            ->limit(50)
            ->get();

        $interval = 1800;
        $incomplete = DB::table('peers')
            ->where('info_hash', $hexInfo)
            ->where('left_bytes', '>', 0)
            ->count();
        $complete = DB::table('peers')
            ->where('info_hash', $hexInfo)
            ->where('left_bytes', '=', 0)
            ->count();

        $responsePayload = [
            'interval' => $interval,
            'complete' => $complete,
            'incomplete' => $incomplete,
        ];

        if ($compact === 1) {
            $binaryPeers = '';

            foreach ($rows as $row) {
                if (filter_var($row->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $binaryPeers .= pack('Nn', (int) ip2long($row->ip), (int) $row->port);
                }
            }

            $responsePayload['peers'] = $binaryPeers;
        } else {
            $list = [];

            foreach ($rows as $row) {
                $list[] = [
                    'ip' => $row->ip,
                    'port' => (int) $row->port,
                ];
            }

            $responsePayload['peers'] = $list;
        }

        return $this->bencode($responsePayload);
    }

    public function scrape(Request $request): Response
    {
        if (Config::get('tracker.mode') === 'external') {
            $url = Config::get('tracker.external_announce');

            if (! is_string($url) || $url === '') {
                return response('tracker misconfigured', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            if (Str::startsWith($url, ['http://', 'https://'])) {
                return redirect()->away($url);
            }

            return $this->bencode([
                'failure reason' => 'use external tracker',
                'announce' => $url,
            ]);
        }

        $infoHash = $this->rawBin($request->query('info_hash', ''));

        if (strlen($infoHash) !== 20) {
            return $this->bencode(['failure reason' => 'invalid info_hash']);
        }

        $hexInfo = bin2hex($infoHash);
        $incomplete = DB::table('peers')
            ->where('info_hash', $hexInfo)
            ->where('left_bytes', '>', 0)
            ->count();
        $complete = DB::table('peers')
            ->where('info_hash', $hexInfo)
            ->where('left_bytes', '=', 0)
            ->count();
        $downloaded = 0;

        return $this->bencode([
            'files' => [
                $infoHash => [
                    'complete' => $complete,
                    'downloaded' => $downloaded,
                    'incomplete' => $incomplete,
                ],
            ],
        ]);
    }

    private function rawBin(?string $value): string
    {
        return rawurldecode($value ?? '');
    }

    private function bencode(array $data): Response
    {
        return response(Bencode::encode($data), Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }
}

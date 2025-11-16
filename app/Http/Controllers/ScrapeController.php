<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BencodeService;
use App\Services\ScrapeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScrapeController extends Controller
{
    public function __construct(
        private readonly BencodeService $bencode,
        private readonly ScrapeService $scrape,
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $infoHashes = $this->parseInfoHashes($request);

        if ($infoHashes === []) {
            return $this->failure('At least one info_hash parameter is required.');
        }

        $payload = $this->scrape->buildResponse($infoHashes);

        return $this->success($payload);
    }

    /**
     * @return array<int, string>
     */
    private function parseInfoHashes(Request $request): array
    {
        $raw = $request->query('info_hash');

        if ($raw === null) {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];
        $hashes = [];

        foreach ($values as $value) {
            if (!is_string($value) || strlen($value) !== 20) {
                continue;
            }

            $hashes[] = strtoupper(bin2hex($value));
        }

        return array_values(array_unique($hashes));
    }

    private function success(array $payload): Response
    {
        return $this->bencodedResponse($payload);
    }

    private function failure(string $message): Response
    {
        return $this->bencodedResponse(['failure reason' => $message]);
    }

    private function bencodedResponse(array $payload): Response
    {
        return response(
            $this->bencode->encode($payload),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}

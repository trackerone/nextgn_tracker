<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BencodeService;
use App\Tracker\Announce\AnnouncePipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AnnounceController extends Controller
{
    public function __construct(
        private readonly BencodeService $bencode,
        private readonly AnnouncePipeline $pipeline,
    ) {}

    public function __invoke(Request $request, string $passkey): Response
    {
        $result = $this->pipeline->handle($request, $passkey);

        return response(
            $this->bencode->encode($result->payload),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }
}

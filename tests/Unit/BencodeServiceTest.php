<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BencodeService;
use PHPUnit\Framework\TestCase;

class BencodeServiceTest extends TestCase
{
    public function test_it_encodes_scalars_and_lists(): void
    {
        $service = new BencodeService();

        $this->assertSame('i42e', $service->encode(42));
        $this->assertSame('4:test', $service->encode('test'));
        $this->assertSame('li1ei2ei3ee', $service->encode([1, 2, 3]));
    }

    public function test_it_encodes_dictionaries_sorted_by_key(): void
    {
        $service = new BencodeService();

        $payload = [
            'peers' => [],
            'interval' => 60,
            'complete' => 1,
        ];

        $this->assertSame('d8:completei1e8:intervali60e5:peerslee', $service->encode($payload));
    }
}

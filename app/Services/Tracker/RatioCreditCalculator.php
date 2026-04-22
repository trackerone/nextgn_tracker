<?php

declare(strict_types=1);

namespace App\Services\Tracker;

final class RatioCreditCalculator
{
    /**
     * @return array{uploaded: int, downloaded: int}
     */
    public function calculate(int $uploadedDelta, int $downloadedDelta, bool $isFreeleech): array
    {
        return [
            'uploaded' => $uploadedDelta,
            'downloaded' => $isFreeleech ? 0 : $downloadedDelta,
        ];
    }
}

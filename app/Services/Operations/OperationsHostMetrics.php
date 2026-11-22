<?php

declare(strict_types=1);

namespace App\Services\Operations;

/**
 * Host-only, read-only Linux metrics. Never executes shell commands or reads secrets.
 * Unknown is returned on systems without /proc.
 */
final class OperationsHostMetrics
{
    /**
     * @return array{memory_total_bytes:int|null,memory_available_bytes:int|null,cpu_cores:int|null,load_1m:float|null,rx_bytes:int|null,tx_bytes:int|null}
     */
    public function collect(): array
    {
        $memory = $this->boundedFile('/proc/meminfo', 8192);
        $cpu = $this->boundedFile('/proc/stat', 65536);
        $network = $this->boundedFile('/proc/net/dev', 32768);
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        $total = $this->memValue($memory, 'MemTotal');
        $available = $this->memValue($memory, 'MemAvailable');
        $cores = $cpu === null ? null : preg_match_all('/^cpu[0-9]+\s/m', $cpu);
        $cores = $cores === false || $cores === 0 ? null : $cores;

        $rx = null;
        $tx = null;

        if ($network !== null) {
            $rx = 0;
            $tx = 0;
            foreach (array_slice(explode("\n", $network), 2) as $line) {
                if (preg_match('/^\s*(\S+):\s*(\d+)\s+(?:\d+\s+){7}(\d+)/', $line, $m) !== 1) {
                    continue;
                }
                if ($m[1] === 'lo') {
                    continue;
                }
                $rx += (int) $m[2];
                $tx += (int) $m[3];
            }
        }

        return [
            'memory_total_bytes' => $total,
            'memory_available_bytes' => $available,
            'cpu_cores' => $cores,
            'load_1m' => $load === false ? null : round((float) $load[0], 2),
            'rx_bytes' => $rx,
            'tx_bytes' => $tx,
        ];
    }

    private function boundedFile(string $path, int $bytes): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $contents = fread($handle, $bytes);

            return $contents === false ? null : $contents;
        } finally {
            fclose($handle);
        }
    }

    private function memValue(?string $content, string $name): ?int
    {
        if ($content === null || preg_match('/^'.preg_quote($name, '/').':\s+(\d+)\s+kB/m', $content, $match) !== 1) {
            return null;
        }

        return (int) $match[1] * 1024;
    }
}

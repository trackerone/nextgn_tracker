<?php

declare(strict_types=1);

namespace App\Support\Operations;

use Illuminate\Support\Carbon;

final class RecentApplicationLogs
{
    private const MAX_FILES = 4;

    private const MAX_BYTES_PER_FILE = 524288;

    private const MAX_ENTRIES = 800;

    public function __construct(private readonly SensitiveLogRedactor $redactor) {}

    /**
     * A deliberately bounded, read-only tail of Laravel logs. No client-supplied paths.
     *
     * @return array<int, array{time: string, severity: string, source: string, message: string, reference: string}>
     */
    public function recent(int $minutes, string $search = '', string $level = ''): array
    {
        $directory = realpath(storage_path('logs'));
        if ($directory === false || ! is_dir($directory)) {
            return [];
        }

        $matches = glob($directory.DIRECTORY_SEPARATOR.'laravel*.log') ?: [];
        $files = array_values(array_filter($matches, static function (string $path) use ($directory): bool {
            $resolved = realpath($path);

            return $resolved !== false
                && dirname($resolved) === $directory
                && ! is_link($path)
                && is_file($path)
                && is_readable($path)
                && preg_match('/^laravel(?:-\d{4}-\d{2}-\d{2})?\.log$/', basename($path)) === 1;
        }));

        usort($files, static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        $since = now()->subMinutes($minutes);
        $entries = [];

        foreach (array_slice($files, 0, self::MAX_FILES) as $path) {
            $fileEntries = [];
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                continue;
            }

            try {
                $size = filesize($path);
                if ($size === false) {
                    continue;
                }

                if ($size > self::MAX_BYTES_PER_FILE) {
                    fseek($handle, -self::MAX_BYTES_PER_FILE, SEEK_END);
                    // Discard the potentially truncated first line.
                    fgets($handle);
                }

                while (($line = fgets($handle)) !== false) {
                    if (preg_match(
                        '/^\[(?<time>[^\]]+)\]\s+(?<env>[A-Za-z0-9_-]+)\.(?<severity>debug|info|notice|warning|error|critical|alert|emergency):\s*(?<message>.*)$/i',
                        $line,
                        $parts
                    ) !== 1) {
                        // Stack traces and multiline context are deliberately not rendered.
                        continue;
                    }

                    try {
                        $time = Carbon::parse($parts['time']);
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($time->lessThan($since)) {
                        continue;
                    }

                    $severity = strtoupper($parts['severity']);
                    if ($level !== '' && strtolower($severity) !== $level) {
                        continue;
                    }

                    $message = $this->redactor->text(trim($parts['message']));
                    if ($search !== '' && stripos($message, $search) === false) {
                        continue;
                    }

                    $fileEntries[] = [
                        'time' => $time->toDateTimeString(),
                        'severity' => $severity,
                        'source' => 'Application',
                        'message' => $message,
                        'reference' => basename($path),
                    ];

                    // A burst must not push the newest events off screen.
                    if (count($fileEntries) > self::MAX_ENTRIES) {
                        array_shift($fileEntries);
                    }
                }
            } finally {
                fclose($handle);
            }

            $entries = array_merge($entries, $fileEntries);
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($b['time'], $a['time']));

        return array_slice($entries, 0, 75);
    }
}

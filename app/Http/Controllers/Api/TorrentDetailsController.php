<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Torrent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

final class TorrentDetailsController extends Controller
{
    public function show(string $torrent): JsonResponse
    {
        $model = Torrent::query()
            ->visible()
            ->with(['category', 'uploader'])
            ->where(static function ($query) use ($torrent): void {
                $query->where('id', $torrent)->orWhere('slug', $torrent);
            })
            ->firstOrFail();

        [$files, $fileCount] = $this->resolveFiles($model);

        return response()->json([
            'data' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
                'description' => (string) ($model->description ?? ''),
                'category' => $model->category === null ? null : [
                    'id' => $model->category->id,
                    'name' => $model->category->name,
                    'slug' => $model->category->slug,
                ],
                'type' => $model->type,
                'size_bytes' => (int) $model->size_bytes,
                'size_human' => $model->formatted_size,
                'seeders' => (int) $model->seeders,
                'leechers' => (int) $model->leechers,
                'completed' => (int) $model->completed,
                'freeleech' => (bool) $model->freeleech,
                'uploaded_at' => $model->uploadedAtForDisplay()?->toISOString(),
                'uploaded_at_human' => $model->uploadedAtForDisplay()?->diffForHumans(),
                'uploader' => $model->uploader === null ? null : [
                    'id' => $model->uploader->id,
                    'name' => $model->uploader->name,
                ],
                'info_hash' => strtolower((string) $model->info_hash),
                'file_count' => $fileCount,
                'files' => $files,
                'magnet_url' => 'magnet:?xt=urn:btih:'.strtolower((string) $model->info_hash),
                'download_url' => '/api/torrents/'.$model->id.'/download',
            ],
        ]);
    }

    /**
     * @return array{0: array<int, array{path: string, size_bytes: int, size_human: string}>, 1: int}
     */
    private function resolveFiles(Torrent $torrent): array
    {
        if (! method_exists($torrent, 'files')) {
            return [[], 0];
        }

        $files = $torrent->files;

        if (! $files instanceof Collection) {
            return [[], 0];
        }

        $mapped = $files->map(function (mixed $file): array {
            $sizeBytes = (int) (data_get($file, 'size_bytes', 0));

            return [
                'path' => (string) data_get($file, 'path', ''),
                'size_bytes' => $sizeBytes,
                'size_human' => $this->formatBytes($sizeBytes),
            ];
        })->values()->all();

        return [$mapped, count($mapped)];
    }

    private function formatBytes(int $bytes): string
    {
        $size = max(0, $bytes);
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        $precision = $unitIndex === 0 ? 0 : 2;

        return number_format($size, $precision).' '.$units[$unitIndex];
    }
}

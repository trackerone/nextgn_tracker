<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TorrentBrowseRequest;
use App\Models\Category;
use App\Models\Torrent;
use App\Services\Security\SanitizationService;
use App\Support\ContentSafety;
use Illuminate\Contracts\View\View;

class TorrentController extends Controller
{
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    public function __construct(private readonly SanitizationService $sanitizer) {}

    public function index(TorrentBrowseRequest $request): View
    {
        $orderMap = config('search.order_aliases', []);
        $allowedSortFields = config('search.allowed_sort_fields', []);
        $defaultOrderKey = array_key_first($orderMap) ?? 'created';

        $validated = $request->validated();

        $searchTerm = isset($validated['q']) ? $this->sanitizer->sanitizeString($validated['q']) : null;
        $searchTerm = $searchTerm !== '' ? $searchTerm : null;

        $orderKey = $validated['order'] ?? $defaultOrderKey;
        if (! array_key_exists($orderKey, $orderMap)) {
            $orderKey = $defaultOrderKey;
        }

        $orderColumn = $orderMap[$orderKey] ?? 'uploaded_at';
        if ($allowedSortFields !== [] && ! in_array($orderColumn, $allowedSortFields, true)) {
            $orderColumn = $allowedSortFields[0];
        }

        $direction = $validated['direction'] ?? 'desc';
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $perPage = (int) ($validated['per_page'] ?? config('search.default_per_page', 25));
        $perPage = max(1, min($perPage, (int) config('search.max_per_page', 100)));

        $filters = [
            'q' => $searchTerm,
            'type' => $validated['type'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'order' => $orderKey,
            'direction' => $direction,
            'per_page' => $perPage,
        ];

        $torrentsQuery = Torrent::query()
            ->with('category')
            ->visible();

        if ($filters['q'] !== null) {
            $escaped = $this->escapeLike($filters['q']);
            $likeTerm = '%'.$escaped.'%';

            $torrentsQuery->where(static function ($query) use ($filters, $likeTerm): void {
                $query->where('name', 'like', $likeTerm)
                    ->orWhereJsonContains('tags', $filters['q']);
            });
        }

        if ($filters['type'] !== null) {
            $torrentsQuery->where('type', $filters['type']);
        }

        if ($filters['category_id'] !== null) {
            $torrentsQuery->where('category_id', $filters['category_id']);
        }

        $torrents = $torrentsQuery
            ->orderBy($orderColumn, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('torrents.index', [
            'torrents' => $torrents,
            'filters' => $filters,
            'types' => self::TYPES,
            'categories' => $categories,
        ]);
    }

    public function show(Torrent $torrent): View
    {
        $this->authorize('view', $torrent);

        return view('torrents.show', [
            'torrent' => $torrent,
            'descriptionHtml' => ContentSafety::markdownToSafeHtml($torrent->description),
            'nfoText' => ContentSafety::nfoToSafeText($torrent->nfo_text),
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

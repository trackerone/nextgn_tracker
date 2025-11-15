<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Torrent;
use App\Services\Security\SanitizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TorrentController extends Controller
{
    private const TYPES = ['movie', 'tv', 'music', 'game', 'software', 'other'];

    public function __construct(private readonly SanitizationService $sanitizer)
    {
    }

    public function index(Request $request): View
    {
        $orderMap = [
            'created' => 'uploaded_at',
            'size' => 'size_bytes',
            'seeders' => 'seeders',
            'leechers' => 'leechers',
            'completed' => 'completed',
        ];

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'order' => ['nullable', Rule::in(array_keys($orderMap))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $searchTerm = isset($validated['q']) ? $this->sanitizer->sanitizeString($validated['q']) : null;
        $searchTerm = $searchTerm !== '' ? $searchTerm : null;

        $filters = [
            'q' => $searchTerm,
            'type' => $validated['type'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'order' => $validated['order'] ?? 'created',
            'direction' => $validated['direction'] ?? 'desc',
        ];

        $torrentsQuery = Torrent::query()
            ->with('category')
            ->displayable();

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

        $orderColumn = $orderMap[$filters['order']] ?? $orderMap['created'];
        $direction = $filters['direction'];

        $torrents = $torrentsQuery
            ->orderBy($orderColumn, $direction)
            ->orderByDesc('id')
            ->paginate(25)
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
        abort_unless($torrent->isDisplayable(), 404);

        $descriptionHtml = null;

        if ($torrent->description !== null) {
            $descriptionHtml = nl2br(e($torrent->description));
        }

        return view('torrents.show', [
            'torrent' => $torrent,
            'descriptionHtml' => $descriptionHtml,
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}

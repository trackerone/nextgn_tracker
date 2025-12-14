<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Torrent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class TorrentController extends Controller
{
    public function index(Request $request): Response|JsonResponse|View
    {
        $query = Torrent::query()
            ->visible()
            ->latest('uploaded_at');

        if ($request->expectsJson()) {
            return response()->json($query->get());
        }

        $torrents = $query->paginate(25);

        $types = Torrent::query()
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();

        $categories = Category::query()
            ->orderBy('name')
            ->get();

        return response()->view('torrents.index', [
            'torrents' => $torrents,
            'types' => $types,
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, string $torrent): Response|JsonResponse|View
    {
        $model = Torrent::query()
            ->where('id', $torrent)
            ->orWhere('slug', $torrent)
            ->firstOrFail();

        $user = $request->user();
        $isStaff = $user !== null && method_exists($user, 'isStaff') && $user->isStaff();

        // Visible for everyone; non-visible only for staff
        if (! $model->isVisible() && ! $isStaff) {
            abort(404);
        }

        if ($request->expectsJson()) {
            return response()->json($model);
        }

        $descriptionText = (string) ($model->description ?? '');
        $descriptionHtml = nl2br(e($descriptionText));

        $nfoText = (string) ($model->nfo_text ?? '');
        $nfoHtml = nl2br(e($nfoText));

        return response()->view('torrents.show', [
            'torrent' => $model,
            'descriptionHtml' => $descriptionHtml,
            'nfoText' => $nfoText,
            'nfoHtml' => $nfoHtml,
        ]);
    }
}

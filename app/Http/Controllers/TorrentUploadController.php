<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\Category;
use App\Services\TorrentUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TorrentUploadController extends Controller
{
    public function create(): View
    {
        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('torrents.upload', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, TorrentUploadService $service): RedirectResponse
    {
        $validated = $request->validate([
            'torrent' => ['required', 'file', 'mimetypes:application/x-bittorrent,application/octet-stream'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $file = $request->file('torrent');

        if ($file === null) {
            throw ValidationException::withMessages([
                'torrent' => 'No torrent file was provided.',
            ]);
        }

        try {
            $torrent = $service->handle($file, $request->user(), [
                'category_id' => $validated['category_id'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);
        } catch (TorrentAlreadyExistsException $exception) {
            return redirect()
                ->route('torrents.show', $exception->torrent->slug)
                ->with('status', 'Torrent already exists – redirected to the existing entry.');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'torrent' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('torrents.show', $torrent->slug)
            ->with('status', 'Torrent uploaded and awaiting approval.');
    }
}

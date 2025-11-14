<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTorrentStateRequest;
use App\Models\Torrent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TorrentModerationController extends Controller
{
    public function index(): View
    {
        $torrents = Torrent::query()
            ->latest()
            ->paginate(25);

        return view('admin.torrents.index', [
            'torrents' => $torrents,
        ]);
    }

    public function update(UpdateTorrentStateRequest $request, Torrent $torrent): RedirectResponse
    {
        $payload = $request->validated();

        $torrent->fill($payload);
        $torrent->save();

        return redirect()
            ->route('admin.torrents.index')
            ->with('status', 'Torrent updated.');
    }
}

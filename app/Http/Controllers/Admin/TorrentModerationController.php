<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTorrentStateRequest;
use App\Models\Torrent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TorrentModerationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString() ?: 'pending';

        $torrents = Torrent::query()
            ->with(['uploader', 'category'])
            ->when($filter === 'pending', static function ($query): void {
                $query->where('is_banned', false)->where('is_approved', false);
            })
            ->when($filter === 'approved', static function ($query): void {
                $query->where('is_banned', false)->where('is_approved', true);
            })
            ->when($filter === 'banned', static function ($query): void {
                $query->where('is_banned', true);
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.torrents.index', [
            'torrents' => $torrents,
            'filter' => $filter,
        ]);
    }

    public function update(UpdateTorrentStateRequest $request, Torrent $torrent): RedirectResponse
    {
        $payload = $request->validated();

        $torrent->fill($payload);
        $torrent->save();

        return redirect()
            ->route('admin.torrents.index', ['filter' => $request->input('filter')])
            ->with('status', 'Torrent updated.');
    }
}

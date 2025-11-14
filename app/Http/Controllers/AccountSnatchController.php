<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountSnatchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $snatches = $user->userTorrents()
            ->with('torrent')
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->paginate(15);

        return view('account.snatches', [
            'snatches' => $snatches,
            'userStats' => [
                'uploaded' => $user->totalUploaded(),
                'downloaded' => $user->totalDownloaded(),
                'ratio' => $user->ratio(),
                'class' => $user->userClass(),
            ],
        ]);
    }
}

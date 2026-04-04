<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Torrent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MyUploadsController extends Controller
{
    public function index(Request $request): View
    {
        $uploads = Torrent::query()
            ->where('user_id', $request->user()?->id)
            ->latest('created_at')
            ->paginate(25);

        return view('torrents.my-uploads', [
            'uploads' => $uploads,
        ]);
    }
}

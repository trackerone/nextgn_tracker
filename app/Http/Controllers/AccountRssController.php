<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AccountRssController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return response()->view('account.rss', [
            'user' => $user,
            'feedUrl' => $user->rss_token !== null ? route('rss.feed', ['token' => $user->rss_token]) : null,
        ]);
    }

    public function rotate(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $user->rotateRssToken();

        return redirect()
            ->route('account.rss.index')
            ->with('status', 'RSS token updated. Old feed URLs are now invalid.');
    }
}

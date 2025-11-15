<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountInviteController extends Controller
{
    public function index(Request $request): View
    {
        $invites = $request->user()
            ->sentInvites()
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('account.invites', [
            'invites' => $invites,
        ]);
    }
}

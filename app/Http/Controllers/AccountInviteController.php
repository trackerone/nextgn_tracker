<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invites\IssueInviteAction;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
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
            'canIssueInvites' => PermissionService::allow($request->user(), 'invites.create'),
        ]);
    }

    public function store(Request $request, IssueInviteAction $issue): RedirectResponse
    {
        // Delegated member invitations are deliberately single-use and short-lived;
        // staff retain the existing multi-use issuance controls in their panel.
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $issue->execute(
            $request->user(),
            1,
            now()->addDays(7)->toDateTimeString(),
            $validated['notes'] ?? null,
        );

        return redirect()->route('account.invites')->with('status', 'Invitation created. Copy the code from your invitation list.');
    }
}

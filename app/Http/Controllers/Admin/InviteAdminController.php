<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Invites\IssueInviteAction;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class InviteAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->lower()->toString();

        $invites = $this->queryInvites($status)
            ->with('inviter')
            ->paginate(20)
            ->withQueryString();

        return view('admin.invites.index', [
            'invites' => $invites,
            'status' => $status,
            'canIssueInvites' => PermissionService::allow($request->user(), 'invites.create'),
        ]);
    }

    public function store(Request $request, IssueInviteAction $issue): RedirectResponse
    {
        $validated = $request->validate([
            'max_uses' => ['required', 'integer', 'min:1', 'max:10000'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invite = $issue->execute(
            $request->user(),
            (int) $validated['max_uses'],
            $validated['expires_at'] ?? null,
            $validated['notes'] ?? null,
        );

        $destination = $request->user()?->isSysop()
            ? route('sysop.permissions.index', ['section' => 'invitations']).'#invitations'
            : route('admin.invites.index');

        return Redirect::to($destination)
            ->with('status', sprintf('Invite %s created', $invite->code));
    }

    private function queryInvites(?string $status): Builder
    {
        $query = Invite::query()->orderByDesc('created_at');

        if ($status === 'active') {
            $query->whereColumn('uses', '<', 'max_uses')
                ->where(function (Builder $builder): void {
                    $builder->whereNull('expires_at')
                        ->orWhere('expires_at', '>', Carbon::now());
                });
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now());
        } elseif ($status === 'used') {
            $query->whereColumn('uses', '>=', 'max_uses');
        }

        return $query;
    }
}

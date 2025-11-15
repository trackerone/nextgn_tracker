<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_uses' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $invite = Invite::create([
            'code' => Str::upper(Str::random(24)),
            'inviter_user_id' => $request->user()?->id,
            'max_uses' => $validated['max_uses'],
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return Redirect::route('admin.invites.index')
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

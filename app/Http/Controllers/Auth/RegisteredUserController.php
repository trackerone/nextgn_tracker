<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use App\Services\Tracker\PasskeyService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly PasskeyService $passkeys)
    {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('torrents.index');
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $requiresInvite = !app()->environment('local');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'invite_code' => [$requiresInvite ? 'required' : 'nullable', 'string'],
        ];

        $data = $request->validate($rules);

        $invite = $this->resolveInvite($data['invite_code'] ?? null, $requiresInvite);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'invited_by_id' => $invite?->inviter_user_id,
        ]);

        $this->passkeys->generate($user);

        if ($invite !== null) {
            $invite->increment('uses');
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended('/');
    }

    private function resolveInvite(?string $code, bool $required): ?Invite
    {
        if (!$code) {
            if ($required) {
                throw ValidationException::withMessages([
                    'invite_code' => __('A valid invite is required.'),
                ]);
            }

            return null;
        }

        $invite = Invite::where('code', $code)->first();

        if (!$invite) {
            throw ValidationException::withMessages([
                'invite_code' => __('Invalid invite code.'),
            ]);
        }

        if ($invite->isExpired() || !$invite->hasRemainingUses()) {
            throw ValidationException::withMessages([
                'invite_code' => __('Invite has expired or has no remaining uses.'),
            ]);
        }

        return $invite;
    }
}

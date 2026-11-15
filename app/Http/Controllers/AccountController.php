<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HnrAccount;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Hnr\HnrSettings;
use App\Services\Logging\AuditLogger;
use App\Services\Tracker\PasskeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $timeout = app(\App\Services\Settings\SiteSettingsRepository::class)->getString('tracker.runtime.ghost_peer_timeout_minutes');
        $minutes = $timeout !== '' ? (int) $timeout : (int) config('tracker.ghost_peer_timeout_minutes', 45);
        $active = Peer::query()->where('user_id', $user->id)
            ->where('last_announce_at', '>', now()->subMinutes(max(1, $minutes)));

        return $this->privateView('account.index', [
            'hnrWarnings' => HnrAccount::query()->where('user_id', $user->id)->value('warnings') ?? 0,
            'hnrEnabled' => app(HnrSettings::class)->enabled(),
            'accountUser' => $user,
            'uploaded' => $user->totalUploaded(),
            'downloaded' => $user->totalDownloaded(),
            'ratio' => $user->ratio(),
            'userClass' => $user->userClass(),
            'seeding' => (clone $active)->where('is_seeder', true)->distinct()->count('torrent_id'),
            'leeching' => (clone $active)->where('is_seeder', false)->distinct()->count('torrent_id'),
            'activePeers' => $active->with('torrent')->latest('last_announce_at')->paginate(10, ['*'], 'peers_page'),
            'history' => $user->userTorrents()->with('torrent')->latest('last_announce_at')->paginate(10, ['*'], 'history_page'),
            'completed' => $user->userTorrents()->whereNotNull('completed_at')->count(),
            'uploads' => Torrent::query()->where('user_id', $user->id)->count(),
            'followingCount' => $user->following()->count(),
            'followerCount' => $user->followers()->count(),
            'followedReleases' => $user->torrentFollows()->count(),
        ]);
    }

    public function edit(Request $request): Response
    {
        return $this->privateView('account.settings', ['accountUser' => $request->user()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $user->setRememberToken(Str::random(60));
        }
        $user->save();
        $audit->log('user.account_updated', $user, ['password_changed' => ! empty($data['password'])]);
        $request->session()->regenerate();

        return redirect()->route('account.settings.edit')->with('status', 'Account settings saved.');
    }

    public function access(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $this->privateView('account.access', [
            'accountUser' => $user,
            'announceUrl' => $user->passkey ? route('announce', ['passkey' => $user->passkey]) : null,
            'keys' => $user->apiKeys()->latest()->get(['id', 'label', 'created_at']),
        ]);
    }

    public function rotatePasskey(Request $request, PasskeyService $passkeys): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $passkeys->rotate($user);

        return redirect()->route('account.access')->with('status', 'Tracker passkey replaced. Update the announce URL in every torrent client.');
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NotificationWatchPresetRequest;
use App\Models\NotificationWatchPreset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class NotificationWatchPresetController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return response()->view('account.notification-watch-presets', [
            'presets' => $user->notificationWatchPresets()->latest()->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authenticatedUser($request);

        return response()->view('account.notification-watch-preset-form', [
            'preset' => null,
            'action' => route('account.watch-presets.store'),
            'method' => 'POST',
        ]);
    }

    public function store(NotificationWatchPresetRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        $user->notificationWatchPresets()->create($request->presetPayload());

        return redirect()
            ->route('account.watch-presets.index')
            ->with('status', 'Notification watch preset saved.');
    }

    public function edit(Request $request, NotificationWatchPreset $preset): Response
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        return response()->view('account.notification-watch-preset-form', [
            'preset' => $preset,
            'action' => route('account.watch-presets.update', ['preset' => $preset]),
            'method' => 'PATCH',
        ]);
    }

    public function update(NotificationWatchPresetRequest $request, NotificationWatchPreset $preset): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        $preset->update($request->presetPayload());

        return redirect()
            ->route('account.watch-presets.index')
            ->with('status', 'Notification watch preset updated.');
    }

    public function destroy(Request $request, NotificationWatchPreset $preset): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        $preset->delete();

        return redirect()
            ->route('account.watch-presets.index')
            ->with('status', 'Notification watch preset deleted.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizePreset(NotificationWatchPreset $preset, User $user): void
    {
        abort_unless((int) $preset->user_id === (int) $user->id, 404);
    }
}

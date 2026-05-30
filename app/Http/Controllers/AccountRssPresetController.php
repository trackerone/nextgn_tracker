<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AccountRssPresetRequest;
use App\Models\RssFeedPreset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AccountRssPresetController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return response()->view('account.rss-preset-form', [
            'user' => $user,
            'preset' => null,
            'action' => route('account.rss.presets.store'),
            'method' => 'POST',
        ]);
    }

    public function store(AccountRssPresetRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $payload = $request->presetPayload();

        $user->rssFeedPresets()->create($payload);

        return redirect()
            ->route('account.rss.index')
            ->with('status', 'RSS preset saved.');
    }

    public function edit(Request $request, RssFeedPreset $preset): Response
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        return response()->view('account.rss-preset-form', [
            'user' => $user,
            'preset' => $preset,
            'action' => route('account.rss.presets.update', ['preset' => $preset]),
            'method' => 'PATCH',
        ]);
    }

    public function update(AccountRssPresetRequest $request, RssFeedPreset $preset): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        $preset->update($request->presetPayload());

        return redirect()
            ->route('account.rss.index')
            ->with('status', 'RSS preset updated.');
    }

    public function destroy(Request $request, RssFeedPreset $preset): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePreset($preset, $user);

        $preset->delete();

        return redirect()
            ->route('account.rss.index')
            ->with('status', 'RSS preset deleted.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizePreset(RssFeedPreset $preset, User $user): void
    {
        abort_unless((int) $preset->user_id === (int) $user->id, 404);
    }
}

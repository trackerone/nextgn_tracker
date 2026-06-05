<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SavedIntentRequest;
use App\Models\SavedIntent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SavedIntentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        /** @var Collection<int, SavedIntent> $savedIntents */
        $savedIntents = SavedIntent::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->view('account.saved-intents', [
            'savedIntents' => $savedIntents,
        ]);
    }

    public function store(SavedIntentRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $payload = $request->intentPayload();

        SavedIntent::query()->create([
            'user_id' => $user->id,
            ...$payload,
        ]);

        return redirect()
            ->route('account.saved-intents.index')
            ->with('status', 'Saved view created.');
    }

    public function apply(Request $request, SavedIntent $savedIntent): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeIntent($savedIntent, $user);

        return redirect()->route('torrents.index', $savedIntent->criteria);
    }

    public function destroy(Request $request, SavedIntent $savedIntent): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeIntent($savedIntent, $user);

        $savedIntent->delete();

        return redirect()
            ->route('account.saved-intents.index')
            ->with('status', 'Saved view deleted.');
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorizeIntent(SavedIntent $savedIntent, User $user): void
    {
        abort_unless((int) $savedIntent->user_id === (int) $user->id, 404);
    }
}

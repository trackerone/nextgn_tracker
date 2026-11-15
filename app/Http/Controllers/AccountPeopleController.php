<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AccountPeopleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->view('account.people', [
            'following' => $user->following()->orderBy('name')->paginate(15, ['users.*'], 'following_page'),
            'followers' => $user->followers()->orderBy('name')->paginate(15, ['users.*'], 'followers_page'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);
        abort_if($viewer->is($user), 422, 'You cannot follow yourself.');
        $viewer->following()->syncWithoutDetaching([$user->id]);

        return redirect()->route('account.people')->with('status', 'Member followed.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);
        $viewer->following()->detach($user->id);

        return redirect()->route('account.people')->with('status', 'Member unfollowed.');
    }
}

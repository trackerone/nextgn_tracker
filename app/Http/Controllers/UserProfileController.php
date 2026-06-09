<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

final class UserProfileController extends Controller
{
    public function show(User $user): Response
    {
        return response()->view('users.show', [
            'profileUser' => $user,
        ]);
    }
}

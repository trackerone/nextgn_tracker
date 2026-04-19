<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Torrents\PersonalizedDiscoveryFeedBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PersonalizedDiscoveryController extends Controller
{
    public function __invoke(Request $request, PersonalizedDiscoveryFeedBuilder $feedBuilder): View
    {
        return view('account.discovery', $feedBuilder->buildFor($request->user()));
    }
}

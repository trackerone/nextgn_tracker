<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Torrents\DiscoveryDashboardBuilder;
use App\Services\Torrents\PersonalizedDiscoveryFeedBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PersonalizedDiscoveryController extends Controller
{
    public function __invoke(
        Request $request,
        DiscoveryDashboardBuilder $dashboardBuilder,
        PersonalizedDiscoveryFeedBuilder $feedBuilder
    ): View
    {
        return view('account.discovery', array_merge(
            $dashboardBuilder->build(),
            $feedBuilder->buildFor($request->user())
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsAlerts;
use App\Services\Operations\OperationsHealthService;
use App\Services\Operations\OperationsProbe;
use App\Services\Operations\RuntimeJobRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OperationsDashboardController extends Controller
{
    public function __invoke(
        OperationsHealthService $operationsHealthService,
        RuntimeJobRegistry $runtimeJobRegistry,
        OperationsProbe $probe,
        OperationsAlerts $alerts,
    ): View {
        try {
            $snapshots = DB::table('ops_snapshots')
                ->where('captured_at', '>=', now()->subDay())
                ->orderByDesc('captured_at')
                ->limit(48)
                ->get()
                ->reverse()
                ->values();
        } catch (Throwable) {
            $snapshots = collect();
        }

        return view('sysop.operations.index', [
            'health' => $operationsHealthService->collect(),
            'runtimeJobs' => $runtimeJobRegistry->all(),
            'live' => $probe->collect(),
            'alerts' => $alerts->recent(),
            'snapshots' => $snapshots,
        ]);
    }
}

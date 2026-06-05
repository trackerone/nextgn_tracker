<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsHealthService;
use App\Services\Operations\RuntimeJobRegistry;
use Illuminate\Contracts\View\View;

final class OperationsDashboardController extends Controller
{
    public function __invoke(OperationsHealthService $operationsHealthService, RuntimeJobRegistry $runtimeJobRegistry): View
    {
        return view('sysop.operations.index', [
            'health' => $operationsHealthService->collect(),
            'runtimeJobs' => $runtimeJobRegistry->all(),
        ]);
    }
}

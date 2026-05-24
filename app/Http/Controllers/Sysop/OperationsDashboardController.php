<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsHealthService;
use Illuminate\Contracts\View\View;

final class OperationsDashboardController extends Controller
{
    public function __invoke(OperationsHealthService $operationsHealthService): View
    {
        return view('sysop.operations.index', [
            'health' => $operationsHealthService->collect(),
        ]);
    }
}

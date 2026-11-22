<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsProbe;
use Illuminate\Http\RedirectResponse;

final class OperationsActiveDiagnosticsController extends Controller
{
    public function __invoke(OperationsProbe $probe): RedirectResponse
    {
        // This endpoint is deliberately POST-only, Sysop-only and rate-limited.
        // Normal dashboard and support-bundle GETs never run write probes.
        $status = $probe->collect(activeWriteChecks: true);
        $checks = array_values(array_filter(
            $status['checks'],
            static fn (array $check): bool => in_array($check['id'], ['cache', 'storage'], true)
        ));

        return redirect()->route('sysop.operations.index')->with('operations_active_diagnostics', [
            'checked_at' => $status['checked_at'],
            'checks' => $checks,
        ]);
    }
}

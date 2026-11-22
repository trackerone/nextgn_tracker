<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class OperationsAlertActionController extends Controller
{
    public function __invoke(Request $request, OperationsAlerts $alerts): RedirectResponse
    {
        $validated = $request->validate([
            'alert_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:acknowledge,resolve'],
        ]);

        $changed = $alerts->updateStatus((int) $validated['alert_id'], (string) $validated['action']);

        return redirect()
            ->route('sysop.operations.index', ['section' => 'alerts'])
            ->with('status', $changed ? 'Incident updated.' : 'Incident was already updated or unavailable.');
    }
}

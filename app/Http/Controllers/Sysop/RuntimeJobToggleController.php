<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Logging\AuditLogger;
use App\Services\Operations\RuntimeJobRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

final class RuntimeJobToggleController extends Controller
{
    public function __invoke(Request $request, RuntimeJobRegistry $registry, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'job_key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $jobKey = (string) $validated['job_key'];
        $nextEnabled = (bool) $validated['enabled'];
        $job = $registry->find($jobKey);

        if ($job === null) {
            return Redirect::route('sysop.operations.index')->withErrors(['runtime_jobs' => 'Unknown runtime job key.']);
        }

        $previousEnabled = $registry->isEnabled($jobKey);

        if (! $registry->update($jobKey, $nextEnabled)) {
            return Redirect::route('sysop.operations.index')->withErrors(['runtime_jobs' => 'This runtime job is immutable and cannot be modified.']);
        }

        $auditLogger->log('sysop.runtime_job_state_changed', null, [
            'job_key' => $jobKey,
            'previous_state' => $previousEnabled,
            'new_state' => $nextEnabled,
            'changed_at' => now()->toIso8601String(),
        ]);

        return Redirect::route('sysop.operations.index')->with('status', 'Runtime job state updated.');
    }
}

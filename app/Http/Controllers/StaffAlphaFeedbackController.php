<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AlphaFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAlphaFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $severity = $request->query('severity');

        $feedback = AlphaFeedback::query()
            ->with(['reporter:id,name'])
            ->when(
                is_string($status) && in_array($status, AlphaFeedback::STATUSES, true),
                fn ($query) => $query->where('status', $status)
            )
            ->when(
                is_string($severity) && in_array($severity, AlphaFeedback::SEVERITIES, true),
                fn ($query) => $query->where('severity', $severity)
            )
            ->orderByRaw("CASE severity WHEN 'blocker' THEN 0 WHEN 'must_fix' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('staff.alpha-feedback.index', [
            'feedback' => $feedback,
            'statuses' => AlphaFeedback::STATUSES,
            'severities' => AlphaFeedback::SEVERITIES,
            'currentStatus' => is_string($status) ? $status : 'open',
            'currentSeverity' => is_string($severity) ? $severity : null,
        ]);
    }

    public function show(AlphaFeedback $alphaFeedback): View
    {
        $alphaFeedback->load(['reporter:id,name,email', 'statusUpdater:id,name']);

        return view('staff.alpha-feedback.show', [
            'alphaFeedback' => $alphaFeedback,
            'statuses' => AlphaFeedback::STATUSES,
        ]);
    }

    public function update(Request $request, AlphaFeedback $alphaFeedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(AlphaFeedback::STATUSES)],
        ]);

        $alphaFeedback->forceFill([
            'status' => $data['status'],
            'status_updated_by' => $request->user()->id,
            'status_updated_at' => now(),
        ])->save();

        return redirect()
            ->route('staff.alpha-feedback.show', $alphaFeedback)
            ->with('status', 'Alpha feedback status updated.');
    }
}

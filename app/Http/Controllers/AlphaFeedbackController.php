<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AlphaFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlphaFeedbackController extends Controller
{
    public function create(): View
    {
        return view('alpha.feedback.create', [
            'areas' => AlphaFeedback::AREAS,
            'severities' => AlphaFeedback::SEVERITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'area' => ['required', 'string', Rule::in(AlphaFeedback::AREAS)],
            'severity' => ['required', 'string', Rule::in(AlphaFeedback::SEVERITIES)],
            'role' => ['nullable', 'string', 'max:80'],
            'environment' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:160'],
            'steps_to_reproduce' => ['required', 'string', 'max:4000'],
            'expected_result' => ['required', 'string', 'max:4000'],
            'actual_result' => ['required', 'string', 'max:4000'],
            'url_or_context' => ['nullable', 'string', 'max:2000'],
            'blocks_alpha' => ['nullable', 'boolean'],
        ]);

        $data['blocks_alpha'] = $request->boolean('blocks_alpha');
        $data['status'] = 'open';
        $data['user_id'] = $request->user()->id;

        AlphaFeedback::query()->create($data);

        return redirect()
            ->route('alpha.feedback.create')
            ->with('status', 'Alpha feedback recorded for staff review.');
    }
}

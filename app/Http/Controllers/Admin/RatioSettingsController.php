<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\RatioSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RatioSettingsController extends Controller
{
    public function __construct(
        private readonly RatioSettings $ratioSettings,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function edit(): View
    {
        return view('admin.settings.ratio', [
            'values' => [
                'elite_min_ratio' => [
                    'value' => $this->ratioSettings->eliteMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.elite_min_ratio'),
                ],
                'power_user_min_ratio' => [
                    'value' => $this->ratioSettings->powerUserMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_ratio'),
                ],
                'power_user_min_downloaded' => [
                    'value' => $this->ratioSettings->powerUserMinDownloaded(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_downloaded'),
                ],
                'user_min_ratio' => [
                    'value' => $this->ratioSettings->userMinRatio(),
                    'overridden' => Setting::hasOverride('ratio.user_min_ratio'),
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'elite_min_ratio' => ['required', 'numeric', 'min:0', 'max:10'],
            'power_user_min_ratio' => ['required', 'numeric', 'min:0', 'max:10'],
            'power_user_min_downloaded' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'user_min_ratio' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        if (! ($validated['elite_min_ratio'] >= $validated['power_user_min_ratio']
            && $validated['power_user_min_ratio'] >= $validated['user_min_ratio'])) {
            throw ValidationException::withMessages([
                'elite_min_ratio' => 'Elite ratio must be >= Power User ratio, which must be >= User ratio.',
            ]);
        }

        Setting::setValue('ratio.elite_min_ratio', (string) $validated['elite_min_ratio'], $request->user()?->id);
        Setting::setValue('ratio.power_user_min_ratio', (string) $validated['power_user_min_ratio'], $request->user()?->id);
        Setting::setValue('ratio.power_user_min_downloaded', (string) $validated['power_user_min_downloaded'], $request->user()?->id);
        Setting::setValue('ratio.user_min_ratio', (string) $validated['user_min_ratio'], $request->user()?->id);

        $this->ratioSettings->flush();

        $this->auditLogger->log('settings.ratio.updated', null, [
            'user_id' => $request->user()?->id,
            'values' => $validated,
        ]);

        return Redirect::route('admin.settings.ratio.edit')
            ->with('status', 'Ratio settings updated');
    }
}

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
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isSysop()) {
            return Redirect::to(route('sysop.permissions.index').'#class-thresholds');
        }

        return view('admin.settings.ratio', [
            'ratioValues' => [
                'tenure_enabled' => [
                    'value' => $this->ratioSettings->tenureEnabled(),
                    'overridden' => Setting::hasOverride('ratio.tenure_enabled'),
                ],
                'user_min_months' => [
                    'value' => $this->ratioSettings->userMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.user_min_months'),
                ],
                'power_user_min_months' => [
                    'value' => $this->ratioSettings->powerUserMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.power_user_min_months'),
                ],
                'elite_min_months' => [
                    'value' => $this->ratioSettings->eliteMinMonths(),
                    'overridden' => Setting::hasOverride('ratio.elite_min_months'),
                ],
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

        // Optional tenure controls may only be updated by Sysop.
        if ($request->user()?->isSysop() && $request->has('tenure_enabled')) {
            $tenure = $request->validate([
                'tenure_enabled' => ['required', 'boolean'],
                'user_min_months' => ['required', 'integer', 'min:0', 'max:1200'],
                'power_user_min_months' => ['required', 'integer', 'min:0', 'max:1200'],
                'elite_min_months' => ['required', 'integer', 'min:0', 'max:1200'],
            ]);

            if (! ($tenure['user_min_months'] <= $tenure['power_user_min_months']
                && $tenure['power_user_min_months'] <= $tenure['elite_min_months'])) {
                throw ValidationException::withMessages([
                    'user_min_months' => 'User months must be <= Power User months <= Elite months.',
                ]);
            }
        }

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

        if (isset($tenure)) {
            Setting::setValue('ratio.tenure_enabled', (string) $tenure['tenure_enabled'], $request->user()?->id);
            Setting::setValue('ratio.user_min_months', (string) $tenure['user_min_months'], $request->user()?->id);
            Setting::setValue('ratio.power_user_min_months', (string) $tenure['power_user_min_months'], $request->user()?->id);
            Setting::setValue('ratio.elite_min_months', (string) $tenure['elite_min_months'], $request->user()?->id);
        }

        $this->ratioSettings->flush();

        $this->auditLogger->log('settings.ratio.updated', null, [
            'user_id' => $request->user()?->id,
            'values' => $validated,
            'tenure' => $tenure ?? null,
        ]);

        $destination = $request->user()?->isSysop()
            ? route('sysop.permissions.index').'#class-thresholds'
            : route('admin.settings.ratio.edit');

        return Redirect::to($destination)->with('status', 'Ratio settings updated');
    }
}

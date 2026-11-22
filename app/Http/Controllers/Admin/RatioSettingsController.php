<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\RatioSettings;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RatioSettingsController extends Controller
{
    public function __construct(
        private readonly RatioSettings $ratioSettings,
        private readonly AuditLogger $auditLogger,
        private readonly SiteSettingsRepository $settings,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isSysop()) {
            return Redirect::to(route('sysop.permissions.index').'#class-thresholds');
        }

        $overrides = $this->settings->getMany(array_keys(SettingsRegistry::classThresholds()));

        return view('admin.settings.ratio', [
            'ratioValues' => [
                'tenure_enabled' => [
                    'value' => $this->ratioSettings->tenureEnabled(),
                    'overridden' => array_key_exists('ratio.tenure_enabled', $overrides),
                ],
                'user_min_months' => [
                    'value' => $this->ratioSettings->userMinMonths(),
                    'overridden' => array_key_exists('ratio.user_min_months', $overrides),
                ],
                'power_user_min_months' => [
                    'value' => $this->ratioSettings->powerUserMinMonths(),
                    'overridden' => array_key_exists('ratio.power_user_min_months', $overrides),
                ],
                'elite_min_months' => [
                    'value' => $this->ratioSettings->eliteMinMonths(),
                    'overridden' => array_key_exists('ratio.elite_min_months', $overrides),
                ],
                'elite_min_ratio' => [
                    'value' => $this->ratioSettings->eliteMinRatio(),
                    'overridden' => array_key_exists('ratio.elite_min_ratio', $overrides),
                ],
                'power_user_min_ratio' => [
                    'value' => $this->ratioSettings->powerUserMinRatio(),
                    'overridden' => array_key_exists('ratio.power_user_min_ratio', $overrides),
                ],
                'power_user_min_downloaded' => [
                    'value' => $this->ratioSettings->powerUserMinDownloaded(),
                    'overridden' => array_key_exists('ratio.power_user_min_downloaded', $overrides),
                ],
                'user_min_ratio' => [
                    'value' => $this->ratioSettings->userMinRatio(),
                    'overridden' => array_key_exists('ratio.user_min_ratio', $overrides),
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

        $tenure = null;

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

        DB::transaction(function () use ($validated, $tenure, $request): void {
            foreach ($validated as $key => $value) {
                $type = $key === 'power_user_min_downloaded' ? 'int' : 'float';
                $this->settings->set('ratio.'.$key, $value, $type);
            }

            if (isset($tenure)) {
                $this->settings->set('ratio.tenure_enabled', (bool) $tenure['tenure_enabled'], 'bool');
                foreach (['user_min_months', 'power_user_min_months', 'elite_min_months'] as $key) {
                    $this->settings->set('ratio.'.$key, $tenure[$key], 'int');
                }
            }

            $this->auditLogger->log('settings.ratio.updated', null, [
                'user_id' => $request->user()?->id,
                'values' => $validated,
                'tenure' => $tenure ?? null,
            ]);
        });

        $this->ratioSettings->flush();

        $destination = $request->user()?->isSysop()
            ? route('sysop.permissions.index').'#class-thresholds'
            : route('admin.settings.ratio.edit');

        return Redirect::to($destination)->with('status', 'Ratio settings updated');
    }
}

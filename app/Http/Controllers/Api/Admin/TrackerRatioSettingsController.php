<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateTrackerRatioSettingsRequest;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Http\JsonResponse;

final class TrackerRatioSettingsController extends Controller
{
    public function __construct(
        private readonly RatioRulesConfig $ratioRulesConfig,
        private readonly SiteSettingsRepository $settings,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'enforcement_enabled' => $this->ratioRulesConfig->enforcementEnabled(),
            'minimum_download_ratio' => $this->ratioRulesConfig->minimumDownloadRatio(),
            'freeleech_bypass_enabled' => $this->ratioRulesConfig->freeleechBypassEnabled(),
            'no_history_grace_enabled' => $this->ratioRulesConfig->noHistoryGraceEnabled(),
        ]);
    }

    public function update(UpdateTrackerRatioSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->settings->set(RatioRulesConfig::ENFORCEMENT_ENABLED, $validated['enforcement_enabled'], 'bool');
        $this->settings->set(RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO, $validated['minimum_download_ratio'], 'float');
        $this->settings->set(RatioRulesConfig::FREELEECH_BYPASS_ENABLED, $validated['freeleech_bypass_enabled'], 'bool');
        $this->settings->set(RatioRulesConfig::NO_HISTORY_GRACE_ENABLED, $validated['no_history_grace_enabled'], 'bool');

        return $this->show();
    }
}

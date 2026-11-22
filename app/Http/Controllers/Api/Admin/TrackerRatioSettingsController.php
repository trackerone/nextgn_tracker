<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Http\JsonResponse;

final class TrackerRatioSettingsController extends Controller
{
    public function __construct(private readonly RatioRulesConfig $ratioRulesConfig) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'enforcement_enabled' => $this->ratioRulesConfig->enforcementEnabled(),
            'minimum_download_ratio' => $this->ratioRulesConfig->minimumDownloadRatio(),
            'freeleech_bypass_enabled' => $this->ratioRulesConfig->freeleechBypassEnabled(),
            'no_history_grace_enabled' => $this->ratioRulesConfig->noHistoryGraceEnabled(),
        ]);
    }
}

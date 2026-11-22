<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Settings\TrackerRuntimeSettings;
use App\Services\Tracker\RatioRulesConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteSettingsController extends Controller
{
    public function edit(TrackerRuntimeSettings $tracker, RatioRulesConfig $ratio): View
    {
        return view('sysop.settings.edit', ['values' => $this->values($tracker, $ratio)]);
    }

    public function update(
        Request $request,
        TrackerRuntimeSettings $tracker,
        RatioRulesConfig $ratio,
        SiteSettingsRepository $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $values = $request->validate([
            'announce_interval_seconds' => ['required', 'integer', 'min:60', 'max:7200'],
            'ghost_peer_timeout_minutes' => ['required', 'integer', 'min:2', 'max:1440'],
            'scrape_max_info_hashes' => ['required', 'integer', 'min:1', 'max:200'],
            'enforcement_enabled' => ['required', 'boolean'],
            'minimum_download_ratio' => ['required', 'numeric', 'min:0', 'max:10'],
            'freeleech_bypass_enabled' => ['required', 'boolean'],
            'no_history_grace_enabled' => ['required', 'boolean'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $values['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'A reason for the change is required.']);
        }

        if ((int) $values['ghost_peer_timeout_minutes'] * 60 <= (int) $values['announce_interval_seconds']) {
            throw ValidationException::withMessages([
                'ghost_peer_timeout_minutes' => 'Peer timeout must be longer than the announce interval.',
            ]);
        }

        $previous = $this->values($tracker, $ratio);
        DB::transaction(function () use ($values, $previous, $settings, $audit, $request, $reason): void {
            $settings->set(TrackerRuntimeSettings::ANNOUNCE_INTERVAL, $values['announce_interval_seconds'], 'int');
            $settings->set(TrackerRuntimeSettings::GHOST_TIMEOUT, $values['ghost_peer_timeout_minutes'], 'int');
            $settings->set(TrackerRuntimeSettings::SCRAPE_LIMIT, $values['scrape_max_info_hashes'], 'int');
            $settings->set(RatioRulesConfig::ENFORCEMENT_ENABLED, (bool) $values['enforcement_enabled'], 'bool');
            $settings->set(RatioRulesConfig::MINIMUM_DOWNLOAD_RATIO, $values['minimum_download_ratio'], 'float');
            $settings->set(RatioRulesConfig::FREELEECH_BYPASS_ENABLED, (bool) $values['freeleech_bypass_enabled'], 'bool');
            $settings->set(RatioRulesConfig::NO_HISTORY_GRACE_ENABLED, (bool) $values['no_history_grace_enabled'], 'bool');
            $audit->log('sysop.site_settings.updated', null, [
                'user_id' => $request->user()?->id,
                'previous' => $previous,
                'values' => $values,
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.settings.edit')->with('status', 'Site settings saved. New requests use these values; no server restart is required.');
    }

    /**
     * @return array<string, bool|int|float>
     */
    private function values(TrackerRuntimeSettings $tracker, RatioRulesConfig $ratio): array
    {
        return [
            'announce_interval_seconds' => $tracker->announceInterval(),
            'ghost_peer_timeout_minutes' => $tracker->ghostTimeout(),
            'scrape_max_info_hashes' => $tracker->scrapeLimit(),
            'enforcement_enabled' => $ratio->enforcementEnabled(),
            'minimum_download_ratio' => $ratio->minimumDownloadRatio(),
            'freeleech_bypass_enabled' => $ratio->freeleechBypassEnabled(),
            'no_history_grace_enabled' => $ratio->noHistoryGraceEnabled(),
        ];
    }
}

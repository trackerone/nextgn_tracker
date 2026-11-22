<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\SiteSettingsRepository;
use App\Services\Settings\UploadLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UploadLimitsController extends Controller
{
    public function edit(UploadLimits $limits): View
    {
        return view('sysop.upload-settings.edit', [
            'values' => $limits->values(),
            'definitions' => UploadLimits::DEFINITIONS,
        ]);
    }

    public function update(
        Request $request,
        UploadLimits $limits,
        SiteSettingsRepository $settings,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validate([
            'torrent_max_kb' => ['required', 'integer', 'min:64', 'max:8192'],
            'nfo_max_kb' => ['required', 'integer', 'min:16', 'max:2048'],
            'nfo_max_characters' => ['required', 'integer', 'min:1024', 'max:1048576'],
            'audit_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = trim((string) $validated['audit_reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['audit_reason' => 'An audit reason is required.']);
        }

        $before = $limits->values();
        $after = [];
        foreach (UploadLimits::DEFINITIONS as $field => $definition) {
            $after[$field] = (int) $validated[$field];
        }

        DB::transaction(function () use ($settings, $audit, $request, $before, $after, $reason): void {
            foreach (UploadLimits::DEFINITIONS as $field => $definition) {
                $settings->set($definition['key'], $after[$field], 'int');
            }

            $audit->log('sysop.upload_limits.updated', null, [
                'actor_user_id' => $request->user()?->getKey(),
                'previous' => $before,
                'values' => $after,
                'audit_reason' => $reason,
            ]);
        });

        return redirect()->route('sysop.upload-settings.edit')
            ->with('status', 'Upload limits saved. New web and API uploads now use these limits.');
    }
}

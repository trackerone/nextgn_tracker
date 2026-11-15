<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sysop;

use App\Http\Controllers\Controller;
use App\Models\HnrAccount;
use App\Models\HnrObligation;
use App\Models\User;
use App\Services\Hnr\HnrService;
use App\Services\Hnr\HnrSettings;
use App\Services\Logging\AuditLogger;
use App\Services\Settings\SiteSettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class HnrSettingsController extends Controller
{
    public function edit(HnrSettings $settings): Response
    {
        return response()->view('hnr.settings', [
            'rules' => $settings->values(),
            'cases' => HnrObligation::query()->whereNull('fulfilled_at')->whereNull('waived_at')->with(['torrent', 'user'])->latest()->paginate(15),
            'accounts' => HnrAccount::query()->where('warnings', '>', 0)->with('user')->latest()->limit(50)->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, HnrSettings $settings, SiteSettingsRepository $repository, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'seed_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'ratio' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'grace_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'torrent_threshold' => ['required', 'integer', 'min:1', 'max:10000'],
            'warning_interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'warning_decay_days' => ['required', 'integer', 'gte:warning_interval_days', 'max:730'],
            'warnings_before_restriction' => ['required', 'integer', 'min:1', 'max:10'],
            'exempt_staff' => ['required', 'boolean'],
            'exempt_uploaders' => ['required', 'boolean'],
        ]);
        $previous = $settings->values();
        DB::transaction(function () use ($data, $previous, $repository, $audit): void {
            foreach (HnrSettings::DEFAULTS as $key => $default) {
                $type = is_bool($default) ? 'bool' : (is_float($default) ? 'float' : 'int');
                $repository->set('hnr.'.$key, $data[$key], $type);
            }
            if (! $previous['enabled'] && (bool) $data['enabled']) {
                HnrObligation::query()->whereNull('fulfilled_at')->whereNull('waived_at')->update(['last_seen_at' => now(), 'last_counted_at' => now()]);
                HnrAccount::query()->where('warnings', '>', 0)->update(['next_warning_at' => now()->addDays((int) $data['warning_interval_days'])]);
            }
            $audit->log('sysop.hnr_settings.updated', null, ['previous' => $previous, 'values' => $data]);
        });

        return redirect()->route('sysop.hnr.edit')->with('status', 'HnR settings saved. Seed-hour and ratio changes apply to new obligations; existing requirements stay unchanged.');
    }

    public function waive(Request $request, HnrObligation $obligation, HnrService $hnr, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($obligation, $data, $hnr, $audit): void {
            $user = User::query()->findOrFail($obligation->user_id);
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $obligation->update(['waived_at' => now(), 'waiver_reason' => $data['reason']]);
            $hnr->event($user, 'waived', 'Staff waived an obligation: '.$data['reason']);
            $audit->log('sysop.hnr_obligation.waived', $obligation, ['reason' => $data['reason']]);
            $hnr->evaluate($user);
        });

        return redirect()->route('sysop.hnr.edit')->with('status', 'Obligation waived.');
    }

    public function reset(Request $request, User $user, HnrService $hnr, HnrSettings $settings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($user, $data, $hnr, $settings, $audit): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            HnrAccount::query()->updateOrCreate(['user_id' => $user->id], ['warnings' => 0, 'restricted_at' => null, 'decay_at' => null, 'next_warning_at' => now()->addDays($settings->values()['warning_interval_days'])]);
            $hnr->event($user, 'reset', 'Staff reset HnR warnings: '.$data['reason']);
            $audit->log('sysop.hnr_account.reset', $user, ['reason' => $data['reason']]);
        });

        return redirect()->route('sysop.hnr.edit')->with('status', 'Warnings reset. Outstanding seed obligations remain.');
    }
}

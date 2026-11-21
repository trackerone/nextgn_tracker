<section id="ratio-classes" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 sm:p-6">
    <h2 class="text-lg font-semibold text-white">User class thresholds</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">These thresholds control automatic class labels, not access roles. Elite ≥ Power User ≥ User. Download restrictions are configured separately as global tracker rules.</p>
    <form method="POST" action="{{ route('admin.settings.ratio.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('PATCH')
        <div class="grid gap-4 md:grid-cols-3">
            <fieldset class="min-w-0 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                <legend class="px-2 font-semibold">User</legend>
                <label class="block text-sm text-slate-200">Minimum ratio
                    <input name="user_min_ratio" type="number" step="0.01" min="0" max="10" required
                        value="{{ old('user_min_ratio', $ratioValues['user_min_ratio']['value']) }}"
                        class="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                </label>
                <p class="mt-2 text-xs text-slate-400">{{ $ratioValues['user_min_ratio']['overridden'] ? 'Custom setting' : 'Default setting' }}</p>
            </fieldset>
            <fieldset class="min-w-0 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                <legend class="px-2 font-semibold">Power User</legend>
                <label class="block text-sm text-slate-200">Minimum ratio
                    <input name="power_user_min_ratio" type="number" step="0.01" min="0" max="10" required
                        value="{{ old('power_user_min_ratio', $ratioValues['power_user_min_ratio']['value']) }}"
                        class="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                </label>
                <label class="mt-3 block text-sm text-slate-200">Minimum downloaded (MB)
                    <input name="power_user_min_downloaded" type="number" min="0" max="10000000000" required
                        value="{{ old('power_user_min_downloaded', $ratioValues['power_user_min_downloaded']['value']) }}"
                        class="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                </label>
                <p class="mt-2 text-xs text-slate-400">{{ $ratioValues['power_user_min_ratio']['overridden'] || $ratioValues['power_user_min_downloaded']['overridden'] ? 'Custom settings' : 'Default settings' }}</p>
            </fieldset>
            <fieldset class="min-w-0 rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                <legend class="px-2 font-semibold">Elite</legend>
                <label class="block text-sm text-slate-200">Minimum ratio
                    <input name="elite_min_ratio" type="number" step="0.01" min="0" max="10" required
                        value="{{ old('elite_min_ratio', $ratioValues['elite_min_ratio']['value']) }}"
                        class="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                </label>
                <p class="mt-2 text-xs text-slate-400">{{ $ratioValues['elite_min_ratio']['overridden'] ? 'Custom setting' : 'Default setting' }}</p>
            </fieldset>
        </div>
        <button class="rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-slate-950">Save class thresholds</button>
    </form>
</section>

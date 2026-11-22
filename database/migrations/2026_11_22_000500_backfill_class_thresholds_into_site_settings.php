<?php

declare(strict_types=1);

use App\Services\Settings\SettingsRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve owner-defined class thresholds. Existing canonical values win.
     * Legacy rows are intentionally retained for a safe application rollback.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings') === false || Schema::hasTable('site_settings') === false) {
            return;
        }

        $definitions = SettingsRegistry::classThresholds();
        $rows = DB::table('settings')->whereIn('key', array_keys($definitions))->get(['key', 'value']);
        $now = now();

        foreach ($rows as $row) {
            $key = (string) $row->key;
            $definition = $definitions[$key];
            $value = $this->validValue((string) $row->value, $definition);

            // Malformed legacy data must never become an active policy override.
            if ($value === null) {
                continue;
            }

            DB::table('site_settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'type' => $definition['type'],
                'group' => 'member_class',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('settings.ratio');
    }

    /**
     * Roll back application behavior without discarding owner changes made
     * after migration. Canonical rows remain as a non-destructive backup.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings') === false || Schema::hasTable('site_settings') === false) {
            return;
        }

        $rows = DB::table('site_settings')
            ->whereIn('key', array_keys(SettingsRegistry::classThresholds()))
            ->get(['key', 'value']);
        $now = now();

        foreach ($rows as $row) {
            $key = (string) $row->key;
            $value = (string) $row->value;

            if (DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->where('key', $key)->update([
                    'value' => $value,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'updated_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Cache::forget('settings.ratio');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function validValue(string $raw, array $definition): ?string
    {
        if ($definition['type'] === 'bool') {
            $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $value === null ? null : ($value ? 'true' : 'false');
        }

        if ($definition['type'] === 'int') {
            $value = filter_var($raw, FILTER_VALIDATE_INT);
        } else {
            $value = is_numeric($raw) ? (float) $raw : false;
        }

        if ($value === false || (is_float($value) && is_finite($value) === false)) {
            return null;
        }

        if ((isset($definition['min']) && $value < $definition['min'])
            || (isset($definition['max']) && $value > $definition['max'])) {
            return null;
        }

        return (string) $value;
    }
};

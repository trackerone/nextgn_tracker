<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(RefreshDatabase::class)->in('Feature', 'Unit');

beforeEach(function (): void {
    // Laravel 10/11 has withoutVite() on TestCase through InteractsWithViews
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    // Seed roles if the table exists (SQLite in-memory can vary by suite)
    if (Schema::hasTable('roles')) {
        $this->seed(RoleSeeder::class);

        // Ensure the level field matches our mapping (if that column exists in the schema)
        foreach (Role::all() as $role) {
            $mappedLevel = RoleLevel::forSlug($role->slug);

            if ($mappedLevel !== null && $role->level !== $mappedLevel) {
                $role->level = $mappedLevel;
                $role->save();
            }
        }
    }
});

/**
 * Test helper expected by RoleAccessTest.
 * Slug inputs are legacy-style (newbie/uploader1/mod1/admin1/sysop).
 */
function createUserWithRole(string $slug): User
{
    // VIGTIGT:
    // RoleAccessTest arbejder med legacy-slugs (mod1/admin1/uploader1/newbie)
    // Therefore user->role must be a legacy slug, not normalized.
    $user = User::factory()->create([
        'role' => $slug,
    ]);

    // Attach role_id if the roles table is seeded
    if (Schema::hasTable('roles')) {
        $roleModel = Role::query()
            ->where('slug', $slug)
            ->first();

        if ($roleModel !== null) {
            $user->forceFill(['role_id' => $roleModel->id])->save();
            $user->setRelation('role', $roleModel);
        }
    }

    // Mange feature tests forventer verified user
    $user->forceFill(['email_verified_at' => now()])->save();

    return $user->refresh();
}

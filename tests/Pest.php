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
    // Laravel 10/11 har withoutVite() på TestCase via InteractsWithViews
    if (method_exists($this, 'withoutVite')) {
        $this->withoutVite();
    }

    // Seed roles hvis tabellen findes (SQLite in-memory kan variere pr. suite)
    if (Schema::hasTable('roles')) {
        $this->seed(RoleSeeder::class);

        // Sørg for at level-feltet matcher vores mapping (hvis den kolonne findes i schema)
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
    // Derfor skal user->role være legacy slug – ikke normaliseret.
    $user = User::factory()->create([
        'role' => $slug,
    ]);

    // Knyt role_id hvis roles-tabellen er seeded
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

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (User $user): void {
                $this->syncStaffFlags($user);
            })
            ->afterCreating(function (User $user): void {
                $this->syncStaffFlags($user);

                // Persist potential changes made in syncStaffFlags()
                if ($user->isDirty(['is_staff', 'role_id'])) {
                    $user->save();
                }
            });
    }

    public function definition(): array
    {
        // Vi undgår Faker "name" fuldstændigt for at slippe for Unknown format "name"
        $unique = Str::uuid()->toString();

        // Default test-user skal kunne passere EnsureMinimumRole:1 (fx /pm).
        $userRoleId = Role::query()->where('slug', 'user1')->value('id');

        return [
            'name' => 'Test User '.$unique,
            'email' => 'user_'.$unique.'@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),

            // Normaliseret rolle-attribut
            'role' => User::ROLE_USER,

            // Legacy role relation (level-baseret adgang)
            'role_id' => $userRoleId,

            'is_banned' => false,
            'is_disabled' => false,
            'passkey' => substr(hash('sha256', $unique), 0, 32),

            // Hold default false; syncStaffFlags() opgraderer ved staff-roller.
            'is_staff' => false,
        ];
    }

    public function staff(): self
    {
        return $this->state(function (): array {
            // Prefer a known legacy staff slug if present, else just mark is_staff.
            $role = Role::query()->whereIn('slug', ['mod1', 'admin1', 'sysop'])->first();

            return [
                'role' => $role?->slug ?? User::ROLE_MODERATOR,
                'role_id' => $role?->id,
                'is_staff' => true,
            ];
        });
    }

    private function syncStaffFlags(User $user): void
    {
        // If is_staff is already explicitly true/false via state/override, respect it.
        // We only auto-upgrade when the role clearly indicates staff.
        $role = $user->getAttribute('role');

        if (! is_string($role) || $role === '') {
            return;
        }

        $role = strtolower($role);

        $isNormalizedStaff = in_array($role, [
            User::ROLE_MODERATOR,
            User::ROLE_ADMIN,
            User::ROLE_SYSOP,
        ], true);

        $isLegacyStaff = in_array($role, [
            'mod1', 'mod2',
            'admin1', 'admin2',
            'sysop',
        ], true);

        if (! $isNormalizedStaff && ! $isLegacyStaff) {
            return;
        }

        // Ensure staff-flag is true (this alone makes EnsureUserIsStaff pass).
        if (! (bool) $user->is_staff) {
            $user->is_staff = true;
        }

        // Best-effort: align role_id to a legacy slug if roles exist.
        // This helps any level-based checks/policies that lean on role_id.
        $targetSlug = match ($role) {
            User::ROLE_MODERATOR, 'mod1', 'mod2' => 'mod1',
            User::ROLE_ADMIN, 'admin1', 'admin2' => 'admin1',
            User::ROLE_SYSOP, 'sysop' => 'sysop',
            default => null,
        };

        if ($targetSlug === null) {
            return;
        }

        // Only set role_id if it's missing or clearly not staff-level.
        if ($user->role_id === null) {
            $targetRoleId = Role::query()->where('slug', $targetSlug)->value('id');
            if ($targetRoleId !== null) {
                $user->role_id = $targetRoleId;
            }
        }
    }
}

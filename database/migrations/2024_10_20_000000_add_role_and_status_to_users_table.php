<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'role')) {
                // Must be nullable: tests create users with role = null.
                // Do NOT set a DB default; default is handled in the User model when role is not explicitly provided.
                $table->string('role')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false)->after('role');
            }

            if (! Schema::hasColumn('users', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('is_banned');
            }
        });

        // Backfill existing users: map legacy role slugs (from roles table) into normalized users.role values.
        // IMPORTANT: do not overwrite explicit nulls in tests - but this runs only for existing rows.
        DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->select('users.id', 'roles.slug')
            ->orderBy('users.id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $role = User::roleFromLegacySlug($user->slug ?? null);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['role' => $role]);
                }
            }, 'users.id');

        // Ensure any remaining nulls in existing data become 'user' (safe default for legacy rows).
        // This keeps tests working because tests create fresh users after migrations.
        DB::table('users')->whereNull('role')->update(['role' => User::ROLE_USER]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('users', 'role')) {
                $columns[] = 'role';
            }

            if (Schema::hasColumn('users', 'is_banned')) {
                $columns[] = 'is_banned';
            }

            if (Schema::hasColumn('users', 'is_disabled')) {
                $columns[] = 'is_disabled';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

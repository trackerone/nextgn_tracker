<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('user')->after('password');
            $table->boolean('is_banned')->default(false)->after('role');
            $table->boolean('is_disabled')->default(false)->after('is_banned');
        });

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
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_banned', 'is_disabled']);
        });
    }
};

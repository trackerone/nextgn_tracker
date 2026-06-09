<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'public_slug')) {
                $table->string('public_slug')->nullable()->after('name');
            }
        });

        $usedSlugs = [];

        DB::table('users')
            ->select(['id', 'name', 'public_slug'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $user) use (&$usedSlugs): void {
                if (is_string($user->public_slug) && $user->public_slug !== '') {
                    $usedSlugs[$user->public_slug] = true;

                    return;
                }

                $slug = $this->uniquePublicSlug((string) $user->name, $usedSlugs);
                $usedSlugs[$slug] = true;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['public_slug' => $slug]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('public_slug', 'users_public_slug_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'public_slug')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_public_slug_unique');
            $table->dropColumn('public_slug');
        });
    }

    /**
     * @param array<string, bool> $usedSlugs
     */
    private function uniquePublicSlug(string $name, array $usedSlugs): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'user';
        }

        if (ctype_digit($base)) {
            $base = 'user-'.$base;
        }

        $slug = $base;
        $counter = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
};

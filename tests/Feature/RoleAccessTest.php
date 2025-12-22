<?php

declare(strict_types=1);

use App\Support\Roles\RoleLevel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('enforces role gates and middleware expectations', function (string $slug, int $expectedAdmin, int $expectedMod): void {
    $user = createUserWithRole($slug);

    // Sørg for at brugeren er e-mail-verificeret
    $user->forceFill([
        'email_verified_at' => now(),
    ])->save();

    actingAs($user->refresh());

    get('/admin')->assertStatus($expectedAdmin);
    get('/mod')->assertStatus($expectedMod);
})->with([
    ['newbie',     403, 403],
    ['uploader1',  403, 403],
    ['mod1',       403, 200],
    ['admin1',     200, 200],
    ['sysop',      200, 200],
]);

it('calculates the correct level for users', function (string $slug, int $expectedLevel): void {
    $user = createUserWithRole($slug);

    expect(RoleLevel::levelForUser($user))->toBe($expectedLevel);
})->with([
    ['sysop',     12],
    ['admin1',    10],
    ['mod1',       8],
    ['uploader1',  5],
    ['newbie',     0],
]);

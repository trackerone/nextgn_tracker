<?php

declare(strict_types=1);

use App\Support\Roles\RoleLevel;

it('returns the expected level for known slugs', function (string $slug, int $level): void {
    expect(RoleLevel::forSlug($slug))->toBe($level);
})->with([
    ['sysop', 12],
    ['admin2', 11],
    ['admin1', 10],
    ['mod2', 9],
    ['mod1', 8],
    ['uploader3', 7],
    ['uploader2', 6],
    ['uploader1', 5],
    ['user4', 4],
    ['user3', 3],
    ['user2', 2],
    ['user1', 1],
    ['newbie', 0],
]);

it('returns the expected slug for known levels', function (int $level, string $slug): void {
    expect(RoleLevel::forLevel($level))->toBe($slug);
})->with([
    [12, 'sysop'],
    [11, 'admin2'],
    [10, 'admin1'],
    [9, 'mod2'],
    [8, 'mod1'],
    [7, 'uploader3'],
    [6, 'uploader2'],
    [5, 'uploader1'],
    [4, 'user4'],
    [3, 'user3'],
    [2, 'user2'],
    [1, 'user1'],
    [0, 'newbie'],
]);

it('returns null for unknown mappings', function (): void {
    expect(RoleLevel::forSlug('unknown'))->toBeNull()
        ->and(RoleLevel::forLevel(99))->toBeNull();
});

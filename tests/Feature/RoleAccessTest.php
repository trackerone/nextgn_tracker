<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Support\Roles\RoleLevel;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

dataset('roleAccessMatrix', [
    'sysop' => ['sysop', [
        'admin' => 200,
        'mod' => 200,
        'gates' => [
            'isAdmin' => true,
            'isModerator' => true,
            'isUploader' => true,
            'isUser' => true,
        ],
    ]],
    'admin1' => ['admin1', [
        'admin' => 200,
        'mod' => 200,
        'gates' => [
            'isAdmin' => true,
            'isModerator' => true,
            'isUploader' => true,
            'isUser' => true,
        ],
    ]],
    'mod1' => ['mod1', [
        'admin' => 403,
        'mod' => 200,
        'gates' => [
            'isAdmin' => false,
            'isModerator' => true,
            'isUploader' => true,
            'isUser' => true,
        ],
    ]],
    'uploader1' => ['uploader1', [
        'admin' => 403,
        'mod' => 403,
        'gates' => [
            'isAdmin' => false,
            'isModerator' => false,
            'isUploader' => true,
            'isUser' => true,
        ],
    ]],
    'user1' => ['user1', [
        'admin' => 403,
        'mod' => 403,
        'gates' => [
            'isAdmin' => false,
            'isModerator' => false,
            'isUploader' => false,
            'isUser' => true,
        ],
    ]],
    'newbie' => ['newbie', [
        'admin' => 403,
        'mod' => 403,
        'gates' => [
            'isAdmin' => false,
            'isModerator' => false,
            'isUploader' => false,
            'isUser' => false,
        ],
    ]],
]);

it('enforces role gates and middleware expectations', function (string $slug, array $expectations): void {
    $user = createUserWithRole($slug);

    actingAs($user);

    get('/admin')->assertStatus($expectations['admin']);
    get('/mod')->assertStatus($expectations['mod']);

    $gate = Gate::forUser($user);

    foreach ($expectations['gates'] as $ability => $allowed) {
        expect($gate->allows($ability))->toBe($allowed);
    }
})->with('roleAccessMatrix');

it('calculates the correct level for users', function (string $slug, int $level): void {
    $user = createUserWithRole($slug);

    expect(RoleLevel::levelForUser($user))->toBe($level);
})->with([
    ['sysop', 12],
    ['admin1', 10],
    ['mod1', 8],
    ['uploader1', 5],
    ['user1', 1],
    ['newbie', 0],
]);

function createUserWithRole(string $slug): User
{
    $roleId = Role::query()->where('slug', $slug)->value('id');

    if ($roleId === null) {
        throw new InvalidArgumentException("Role '{$slug}' does not exist.");
    }

    return User::query()->create([
        'name' => ucfirst($slug).' Tester',
        'email' => $slug.'@example.com',
        'password' => 'password',
        'role_id' => $roleId,
        'email_verified_at' => now(),
    ]);
}

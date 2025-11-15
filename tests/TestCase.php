<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(
            ['slug' => Role::DEFAULT_SLUG],
            ['name' => 'User', 'level' => 1, 'is_staff' => false],
        );
    }
}

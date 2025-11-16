<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('roles')) {
            Role::query()->firstOrCreate(
                ['slug' => Role::DEFAULT_SLUG],
                ['name' => 'User', 'level' => 1, 'is_staff' => false],
            );
        }
    }

    protected function tearDown(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        parent::tearDown();
    }
}

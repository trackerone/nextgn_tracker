<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force in-memory cache to avoid SQLite table errors
        config()->set('cache.default', 'array');

        // Seed the database so roles, permissions, etc. exist
        $this->seed();
    }

    protected function tearDown(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        parent::tearDown();
    }
}

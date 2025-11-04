<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(Tests\CreatesApplication::class, RefreshDatabase::class, WithFaker::class)->in('Feature');
uses(Tests\CreatesApplication::class)->in('Unit');

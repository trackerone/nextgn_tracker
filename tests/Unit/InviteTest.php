<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_expired_invites(): void
    {
        $invite = Invite::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($invite->isExpired());
    }

    public function test_it_reports_active_invites(): void
    {
        $invite = Invite::factory()->create([
            'expires_at' => now()->addDay(),
        ]);

        $this->assertFalse($invite->isExpired());
    }

    public function test_remaining_use_flag_handles_limits(): void
    {
        $invite = Invite::factory()->create([
            'max_uses' => 2,
            'uses' => 1,
        ]);

        $this->assertTrue($invite->hasRemainingUses());

        $invite->uses = 2;

        $this->assertFalse($invite->hasRemainingUses());
    }
}

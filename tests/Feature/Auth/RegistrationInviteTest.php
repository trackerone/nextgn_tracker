<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_invite(): void
    {
        $inviter = User::factory()->create();
        $invite = Invite::factory()->create([
            'inviter_user_id' => $inviter->id,
            'max_uses' => 1,
            'uses' => 0,
        ]);

        $response = $this->post('/register', [
            'name' => 'Example User',
            'email' => 'user@example.org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invite_code' => $invite->code,
        ]);

        $response->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.org',
            'invited_by_id' => $inviter->id,
        ]);
        $this->assertDatabaseHas('invites', [
            'id' => $invite->id,
            'uses' => 1,
        ]);
    }

    public function test_invalid_invite_is_rejected(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Example User',
            'email' => 'user@example.org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invite_code' => 'does-not-exist',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'user@example.org']);
    }

    public function test_expired_invite_is_rejected(): void
    {
        $invite = Invite::factory()->create([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->post('/register', [
            'name' => 'Expired User',
            'email' => 'expired@example.org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invite_code' => $invite->code,
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'expired@example.org']);
    }

    public function test_used_up_invite_is_rejected(): void
    {
        $invite = Invite::factory()->create([
            'max_uses' => 1,
            'uses' => 1,
        ]);

        $response = $this->post('/register', [
            'name' => 'Used User',
            'email' => 'used@example.org',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'invite_code' => $invite->code,
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'used@example.org']);
    }
}

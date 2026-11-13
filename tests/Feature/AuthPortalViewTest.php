<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AuthPortalViewTest extends TestCase
{
    public function test_guest_sees_floating_login_portal(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('nextgn-auth-card')
            ->assertSee('Private community tracker')
            ->assertSee('Verified releases')
            ->assertSee('Discovery')
            ->assertSee('Active community')
            ->assertSee('Create an account')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_registration_uses_same_portal_without_changing_invite_flow(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('nextgn-auth-card--register')
            ->assertSee('name="invite_code"', false)
            ->assertSee('Already a member?')
            ->assertSee('Log in');
    }
}

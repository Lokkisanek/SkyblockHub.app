<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_is_not_available(): void
    {
        $this->get('/confirm-password')->assertNotFound();
    }

    public function test_confirm_password_post_is_not_available(): void
    {
        $this->post('/confirm-password', [
            'password' => 'password',
        ])->assertNotFound();
    }
}

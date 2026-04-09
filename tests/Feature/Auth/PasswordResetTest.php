<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_routes_are_not_available(): void
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/reset-password/test-token')->assertNotFound();
        $this->assertFalse(Route::has('password.email'));
        $this->assertFalse(Route::has('password.update'));
    }
}

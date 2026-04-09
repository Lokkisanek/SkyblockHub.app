<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_is_not_exposed_in_current_auth_flow(): void
    {
        $this->get('/verify-email')->assertNotFound();
    }

    public function test_email_verification_named_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('verification.verify'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class AuthApiTest extends PlatformTestCase
{
    public function test_register_and_verify_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'username' => 'ada',
            'email' => 'ada@example.com',
            'phone' => '555-0100',
            'password' => 'secret12',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'is_verified' => false]);
        $this->assertDatabaseHas('otps', ['is_used' => false]);

        $otp = Otp::first();

        $verify = $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'ada@example.com',
            'otp' => $otp->code,
        ]);

        $verify->assertOk();
        $this->assertTrue(User::where('email', 'ada@example.com')->first()->is_verified);
    }

    public function test_login_returns_access_token(): void
    {
        $this->createUser(['email' => 'login@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['accessToken', 'user' => ['id', 'email', 'firstName']]);
    }

    public function test_unverified_user_cannot_access_customer_portal(): void
    {
        $user = $this->createUser([
            'is_verified' => false,
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/customers/me')->assertForbidden();
    }

    public function test_verified_user_can_read_profile(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/customers/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}

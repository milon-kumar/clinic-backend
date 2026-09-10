<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class AuthApiTest extends PlatformTestCase
{
    public function test_register_and_verify_email(): void
    {
        Mail::fake();

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
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasTo('ada@example.com') && $mail->type === OtpService::TYPE_EMAIL_VERIFY;
        });

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

    public function test_forgot_password_sends_otp_without_revealing_accounts(): void
    {
        Mail::fake();
        $user = $this->createUser(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('otps', [
            'user_id' => $user->id,
            'type' => OtpService::TYPE_PASSWORD_RESET,
            'is_used' => false,
        ]);
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasTo('reset@example.com') && $mail->type === OtpService::TYPE_PASSWORD_RESET;
        });

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'missing@example.com']);
    }

    public function test_reset_password_with_otp(): void
    {
        Mail::fake();
        $user = $this->createUser(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        $otp = Otp::query()->where('user_id', $user->id)->where('type', OtpService::TYPE_PASSWORD_RESET)->first();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => $otp->code,
            'password' => 'newpass99',
            'passwordConfirmation' => 'newpass99',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass99', $user->fresh()->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'newpass99',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_otp(): void
    {
        Mail::fake();
        $this->createUser(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => '000000',
            'password' => 'newpass99',
            'passwordConfirmation' => 'newpass99',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_register_otp_uses_admin_from_address(): void
    {
        Mail::fake();
        SiteSetting::current()->update([
            'mail_enabled' => true,
            'mail_from_name' => 'Nova Clinic',
            'mail_from_address' => 'noreply@nova.clinic',
        ]);

        $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'username' => 'ada-mail',
            'email' => 'ada-mail@example.com',
            'password' => 'secret12',
        ])->assertCreated();

        Mail::assertSent(OtpMail::class, function (OtpMail $mail) {
            return $mail->hasFrom('noreply@nova.clinic') && $mail->hasTo('ada-mail@example.com');
        });
    }
}

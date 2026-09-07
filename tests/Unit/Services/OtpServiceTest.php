<?php

namespace Tests\Unit\Services;

use App\Models\Otp;
use App\Services\OtpService;
use Illuminate\Support\Facades\Mail;
use Tests\PlatformTestCase;

class OtpServiceTest extends PlatformTestCase
{
    public function test_generate_creates_valid_otp(): void
    {
        Mail::fake();

        $user = $this->createUser(['is_verified' => false, 'email_verified_at' => null]);
        $otp = app(OtpService::class)->generate($user);

        $this->assertDatabaseHas('otps', [
            'user_id' => $user->id,
            'code' => $otp->code,
            'is_used' => false,
        ]);
        $this->assertTrue($otp->isValid());
    }

    public function test_verify_marks_user_verified(): void
    {
        Mail::fake();

        $user = $this->createUser(['is_verified' => false, 'email_verified_at' => null]);
        $otp = app(OtpService::class)->generate($user);

        $verified = app(OtpService::class)->verify($user, $otp->code);

        $this->assertTrue($verified);
        $this->assertTrue($user->fresh()->is_verified);
    }

    public function test_verify_rejects_invalid_code(): void
    {
        $user = $this->createUser();

        $this->assertFalse(app(OtpService::class)->verify($user, '000000'));
    }

    public function test_resend_invalidates_previous_otp(): void
    {
        Mail::fake();

        $user = $this->createUser(['is_verified' => false]);
        $first = app(OtpService::class)->generate($user);
        app(OtpService::class)->resend($user);

        $this->assertTrue(Otp::find($first->id)->is_used);
    }
}

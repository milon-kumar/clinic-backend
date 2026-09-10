<?php

namespace Tests\Unit\Services;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\SiteSetting;
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
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->type === OtpService::TYPE_EMAIL_VERIFY;
        });
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

    public function test_password_reset_otp_does_not_mark_email_verified(): void
    {
        Mail::fake();

        $user = $this->createUser(['is_verified' => false, 'email_verified_at' => null]);
        $otp = app(OtpService::class)->generate($user, OtpService::TYPE_PASSWORD_RESET);

        $ok = app(OtpService::class)->verify($user, $otp->code, OtpService::TYPE_PASSWORD_RESET);

        $this->assertTrue($ok);
        $this->assertFalse($user->fresh()->is_verified);
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

    public function test_disabled_mail_still_stores_otp(): void
    {
        Mail::fake();
        SiteSetting::current()->update(['mail_enabled' => false]);

        $user = $this->createUser(['is_verified' => false, 'email_verified_at' => null]);
        $otp = app(OtpService::class)->generate($user);

        $this->assertTrue($otp->isValid());
        Mail::assertNothingSent();
    }
}

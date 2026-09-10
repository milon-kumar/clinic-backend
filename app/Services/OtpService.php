<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\SiteSetting;
use App\Models\User;

class OtpService
{
    public const OTP_EXPIRY_MINUTES = 15;

    public const TYPE_EMAIL_VERIFY = 'email_verify';

    public const TYPE_PASSWORD_RESET = 'password_reset';

    public function __construct(private MailConfigService $mailConfig) {}

    public function generate(User $user, string $type = self::TYPE_EMAIL_VERIFY): Otp
    {
        Otp::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = Otp::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'is_used' => false,
        ]);

        $this->sendOtpEmail($user, $code, $type);

        return $otp;
    }

    public function verify(User $user, string $code, string $type = self::TYPE_EMAIL_VERIFY): bool
    {
        if (! $this->consume($user, $code, $type)) {
            return false;
        }

        if ($type === self::TYPE_EMAIL_VERIFY) {
            $user->update([
                'is_verified' => true,
                'email_verified_at' => now(),
            ]);
        }

        return true;
    }

    public function consume(User $user, string $code, string $type): bool
    {
        $otp = Otp::query()
            ->where('user_id', $user->id)
            ->where('code', $code)
            ->where('type', $type)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $otp || ! $otp->isValid()) {
            return false;
        }

        $otp->update(['is_used' => true]);

        return true;
    }

    public function resend(User $user, string $type = self::TYPE_EMAIL_VERIFY): Otp
    {
        return $this->generate($user, $type);
    }

    private function sendOtpEmail(User $user, string $code, string $type): void
    {
        $siteName = SiteSetting::query()->value('site_name') ?: 'Elixir Clinic';

        $this->mailConfig->send(
            new OtpMail($code, $type, $siteName),
            $user->email,
        );
    }
}

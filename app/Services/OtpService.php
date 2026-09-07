<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const OTP_EXPIRY_MINUTES = 15;

    public function generate(User $user, string $type = 'email_verify'): Otp
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

        $this->sendOtpEmail($user, $code);

        return $otp;
    }

    public function verify(User $user, string $code, string $type = 'email_verify'): bool
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
        $user->update([
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        return true;
    }

    public function resend(User $user, string $type = 'email_verify'): Otp
    {
        return $this->generate($user, $type);
    }

    private function sendOtpEmail(User $user, string $code): void
    {
        try {
            Mail::raw(
                "Your verification code is: {$code}",
                fn ($message) => $message->to($user->email)->subject('Email Verification Code')
            );
        } catch (\Throwable) {
            // Log mail failures in production; OTP is still stored
        }
    }
}

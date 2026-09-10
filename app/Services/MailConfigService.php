<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailConfigService
{
    public function settings(): ?SiteSetting
    {
        return SiteSetting::query()->first();
    }

    public function shouldSend(?SiteSetting $settings = null): bool
    {
        $settings ??= $this->settings();

        return $settings?->mail_enabled !== false;
    }

    public function apply(?SiteSetting $settings = null): void
    {
        $settings ??= $this->settings();

        if (! $settings) {
            return;
        }

        $fromAddress = $settings->mail_from_address ?: $settings->email ?: config('mail.from.address');
        $fromName = $settings->mail_from_name ?: $settings->site_name ?: config('mail.from.name');

        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);

        if (! $settings->mail_enabled || ! filled($settings->mail_host)) {
            return;
        }

        $encryption = $settings->mail_encryption ?: 'tls';
        $scheme = $encryption === 'ssl' ? 'smtps' : null;

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.scheme', $scheme);
        Config::set('mail.mailers.smtp.host', $settings->mail_host);
        Config::set('mail.mailers.smtp.port', (int) ($settings->mail_port ?: ($encryption === 'ssl' ? 465 : 587)));
        Config::set('mail.mailers.smtp.username', $settings->mail_username);
        Config::set('mail.mailers.smtp.password', $settings->mail_password);

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
    }

    public function send(Mailable $mailable, string $to): bool
    {
        if (! $this->shouldSend()) {
            return false;
        }

        $this->apply();

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mail send failed', [
                'to' => $to,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

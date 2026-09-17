<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Config;

class StripeConfigService
{
    public function settings(): ?SiteSetting
    {
        return SiteSetting::query()->first();
    }

    public function configuredInSettings(?SiteSetting $settings = null): bool
    {
        $settings ??= $this->settings();

        return filled($settings?->stripe_secret_key);
    }

    public function enabled(?SiteSetting $settings = null): bool
    {
        $settings ??= $this->settings();

        if ($settings && $settings->stripe_enabled === false) {
            return false;
        }

        return filled($this->secretKey($settings));
    }

    public function publishableKey(?SiteSetting $settings = null): string
    {
        $settings ??= $this->settings();

        return trim((string) ($settings?->stripe_publishable_key ?? ''));
    }

    public function secretKey(?SiteSetting $settings = null): string
    {
        $settings ??= $this->settings();

        return trim((string) ($settings?->stripe_secret_key ?? ''));
    }

    public function webhookSecret(?SiteSetting $settings = null): string
    {
        $settings ??= $this->settings();

        return trim((string) ($settings?->stripe_webhook_secret ?? ''));
    }

    public function mode(?SiteSetting $settings = null): string
    {
        return str_starts_with($this->secretKey($settings), 'sk_live_') ? 'live' : 'test';
    }

    public const WEBHOOK_PATH = '/api/v1/stripe/webhook';

    public function webhookBaseUrl(?SiteSetting $settings = null): string
    {
        $settings ??= $this->settings();
        $custom = trim((string) ($settings?->stripe_webhook_base_url ?? ''));

        if ($custom !== '') {
            return rtrim($custom, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function webhookUrl(?SiteSetting $settings = null): string
    {
        return $this->webhookBaseUrl($settings).self::WEBHOOK_PATH;
    }

    public function apply(?SiteSetting $settings = null): void
    {
        Config::set('services.stripe.key', $this->publishableKey($settings));
        Config::set('services.stripe.secret', $this->secretKey($settings));
        Config::set('services.stripe.webhook_secret', $this->webhookSecret($settings));
    }

    public function maskKey(string $key): ?string
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        if (strlen($key) <= 12) {
            return str_repeat('•', strlen($key));
        }

        return substr($key, 0, 8).'…'.substr($key, -4);
    }

}

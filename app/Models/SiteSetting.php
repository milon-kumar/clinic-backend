<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'logo',
        'favicon',
        'footer_qr_image',
        'footer_qr_link',
        'footer_qr_label',
        'website_url',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'linkedin_url',
        'phone',
        'whatsapp',
        'email',
        'address',
        'hours',
        'about_title',
        'about_short_desc',
        'about_long_desc',
        'about_image',
        'home_meta_title',
        'home_meta_description',
        'mail_enabled',
        'mail_from_name',
        'mail_from_address',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'stripe_enabled',
        'stripe_publishable_key',
        'stripe_secret_key',
        'stripe_webhook_secret',
        'stripe_webhook_base_url',
    ];

    protected $hidden = [
        'mail_password',
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'Elixir Clinic',
            'logo' => '/Assets/logo.jpg',
            'favicon' => null,
            'footer_qr_image' => null,
            'footer_qr_link' => null,
            'footer_qr_label' => null,
            'website_url' => null,
            'facebook_url' => 'https://www.facebook.com',
            'instagram_url' => 'https://instagram.com',
            'tiktok_url' => 'https://www.tiktok.com',
            'linkedin_url' => 'https://linkedin.com',
            'phone' => '020 3409 2444',
            'whatsapp' => '07352 887444',
            'email' => 'elixirclinic@gmail.com',
            'address' => '63 A Shirland Rd, London W9 2JD',
            'hours' => 'Mon - Sat | 9:00 AM - 9:00 PM',
            'about_title' => 'Our Philosophy',
            'about_short_desc' => 'Expert care. Natural beauty. Treatments on your terms at Elixir Aesthetic Medicine Clinic.',
            'about_long_desc' => 'At our clinic, beauty is more than appearance—it\'s about confidence, comfort, and feeling your best every day. We are committed to providing advanced aesthetic and laser treatments that help you achieve natural-looking results while enhancing your overall well-being. Combining medical expertise with the latest technology, our team of experienced specialists delivers personalized treatments tailored to your unique goals. Whether you are seeking laser hair removal, skin rejuvenation, anti-aging solutions, or cosmetic enhancements, we create customized treatment plans designed around your needs and lifestyle. We believe that everyone deserves professional care in a safe, welcoming, and supportive environment. From your initial consultation to your final results, our focus is on delivering exceptional service, outstanding outcomes, and a seamless experience at every step. Our mission is simple: to help you look refreshed, feel empowered, and enjoy the confidence that comes from loving the skin you\'re in.',
            'about_image' => '/Assets/team.avif',
            'mail_enabled' => true,
            'mail_encryption' => 'tls',
        ];
    }

    protected function casts(): array
    {
        return [
            'mail_enabled' => 'boolean',
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
            'stripe_enabled' => 'boolean',
            'stripe_secret_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();

        if ($row) {
            return $row;
        }

        return static::query()->create(static::defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'siteName' => $this->site_name,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'footerQrImage' => $this->footer_qr_image,
            'footerQrLink' => $this->footer_qr_link,
            'footerQrLabel' => $this->footer_qr_label,
            'websiteUrl' => $this->website_url,
            'facebookUrl' => $this->facebook_url,
            'instagramUrl' => $this->instagram_url,
            'tiktokUrl' => $this->tiktok_url,
            'linkedinUrl' => $this->linkedin_url,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'hours' => $this->hours,
            'aboutTitle' => $this->about_title,
            'aboutShortDesc' => $this->about_short_desc,
            'aboutLongDesc' => $this->about_long_desc,
            'aboutImage' => $this->about_image,
            'homeMetaTitle' => $this->home_meta_title,
            'homeMetaDescription' => $this->home_meta_description,
        ];
    }

    /**
     * Public site fields plus SMTP config. Never includes the mail password.
     *
     * @return array<string, mixed>
     */
    public function toAdminApi(): array
    {
        return [
            ...$this->toApi(),
            'mailEnabled' => (bool) $this->mail_enabled,
            'mailFromName' => $this->mail_from_name,
            'mailFromAddress' => $this->mail_from_address,
            'mailHost' => $this->mail_host,
            'mailPort' => $this->mail_port ?: 587,
            'mailEncryption' => $this->mail_encryption ?: 'tls',
            'mailUsername' => $this->mail_username,
            'mailPassword' => '',
            'mailPasswordSet' => filled($this->mail_password),
            'stripeEnabled' => $this->stripe_enabled !== false,
            'stripePublishableKey' => $this->stripe_publishable_key ?? '',
            'stripeSecretKey' => '',
            'stripeSecretKeySet' => filled($this->stripe_secret_key),
            'stripeWebhookSecret' => '',
            'stripeWebhookSecretSet' => filled($this->stripe_webhook_secret),
            'stripeWebhookBaseUrl' => $this->stripe_webhook_base_url ?? '',
            'stripeDefaultWebhookBaseUrl' => rtrim((string) config('app.url'), '/'),
            'stripeWebhookPath' => '/api/v1/stripe/webhook',
            'stripeWebhookUrl' => app(\App\Services\StripeConfigService::class)->webhookUrl($this),
        ];
    }
}

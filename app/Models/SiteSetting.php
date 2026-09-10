<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'logo',
        'website_url',
        'facebook_url',
        'instagram_url',
        'twitter_url',
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
        'mail_enabled',
        'mail_from_name',
        'mail_from_address',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
    ];

    protected $hidden = [
        'mail_password',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'Elixir Clinic',
            'logo' => '/Assets/logo.jpg',
            'website_url' => null,
            'facebook_url' => 'https://www.facebook.com',
            'instagram_url' => 'https://instagram.com',
            'twitter_url' => 'https://twitter.com',
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
            'websiteUrl' => $this->website_url,
            'facebookUrl' => $this->facebook_url,
            'instagramUrl' => $this->instagram_url,
            'twitterUrl' => $this->twitter_url,
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
        ];
    }
}

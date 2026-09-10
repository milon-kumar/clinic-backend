<?php

namespace Tests\Feature;

use App\Mail\SiteTestMail;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\PlatformTestCase;

class SiteSettingApiTest extends PlatformTestCase
{
    public function test_public_settings_are_created_with_defaults(): void
    {
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.siteName', 'Elixir Clinic')
            ->assertJsonPath('data.email', 'elixirclinic@gmail.com')
            ->assertJsonPath('data.phone', '020 3409 2444')
            ->assertJsonPath('data.aboutTitle', 'Our Philosophy');

        $this->assertDatabaseCount('site_settings', 1);
    }

    public function test_staff_can_read_admin_settings(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'receptionist']));

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.siteName', 'Elixir Clinic');
    }

    public function test_superadmin_updates_site_settings(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->patchJson('/api/v1/admin/settings', [
            'siteName' => 'Nova Clinic',
            'phone' => '020 1111 2222',
            'email' => 'hello@nova.clinic',
            'websiteUrl' => 'https://nova.clinic',
            'facebookUrl' => 'https://facebook.com/nova',
            'aboutTitle' => 'Who we are',
            'aboutShortDesc' => 'Short story.',
            'aboutLongDesc' => 'A longer story about the clinic.',
        ])->assertOk()
            ->assertJsonPath('data.siteName', 'Nova Clinic')
            ->assertJsonPath('data.phone', '020 1111 2222')
            ->assertJsonPath('data.email', 'hello@nova.clinic')
            ->assertJsonPath('data.aboutTitle', 'Who we are');

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.siteName', 'Nova Clinic')
            ->assertJsonPath('data.aboutShortDesc', 'Short story.');
    }

    public function test_branch_staff_cannot_update_site_settings(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'receptionist']));

        $this->patchJson('/api/v1/admin/settings', [
            'siteName' => 'Hacked Clinic',
        ])->assertForbidden();

        $this->assertSame('Elixir Clinic', SiteSetting::current()->site_name);
    }

    public function test_superadmin_uploads_a_logo(): void
    {
        Storage::fake('public');
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $path = $this->post('/api/v1/admin/settings/upload', [
            'kind' => 'logo',
            'file' => UploadedFile::fake()->image('logo.jpg'),
        ])->assertOk()
            ->json('path');

        $this->assertIsString($path);
        $this->assertStringStartsWith('/storage/settings/logo/', $path);
    }

    public function test_public_settings_do_not_include_smtp_secrets(): void
    {
        SiteSetting::current()->update([
            'mail_host' => 'smtp.example.com',
            'mail_username' => 'mailer',
            'mail_password' => 'super-secret',
        ]);

        $data = $this->getJson('/api/v1/settings')->assertOk()->json('data');

        $this->assertArrayNotHasKey('mailHost', $data);
        $this->assertArrayNotHasKey('mailPassword', $data);
        $this->assertArrayNotHasKey('mailUsername', $data);
    }

    public function test_superadmin_saves_mail_config_without_echoing_password(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->patchJson('/api/v1/admin/settings', [
            'mailEnabled' => true,
            'mailFromName' => 'Elixir Mail',
            'mailFromAddress' => 'noreply@elixir.clinic',
            'mailHost' => 'smtp.example.com',
            'mailPort' => 587,
            'mailEncryption' => 'tls',
            'mailUsername' => 'mailer@elixir.clinic',
            'mailPassword' => 'app-password',
        ])->assertOk()
            ->assertJsonPath('data.mailHost', 'smtp.example.com')
            ->assertJsonPath('data.mailFromAddress', 'noreply@elixir.clinic')
            ->assertJsonPath('data.mailPassword', '')
            ->assertJsonPath('data.mailPasswordSet', true);

        $settings = SiteSetting::current();
        $this->assertSame('app-password', $settings->mail_password);

        $this->patchJson('/api/v1/admin/settings', [
            'mailHost' => 'smtp.example.com',
            'mailPassword' => '',
        ])->assertOk()
            ->assertJsonPath('data.mailPasswordSet', true);

        $this->assertSame('app-password', SiteSetting::current()->mail_password);
    }

    public function test_superadmin_can_send_a_test_email(): void
    {
        Mail::fake();
        $this->actingAsUser($this->createUser(['role' => 'superadmin', 'email' => 'admin@example.com']));

        $this->postJson('/api/v1/admin/settings/test-email', [
            'email' => 'probe@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'Test email sent to probe@example.com.');

        Mail::assertSent(SiteTestMail::class, function (SiteTestMail $mail) {
            return $mail->hasTo('probe@example.com');
        });

        $this->actingAsUser($this->createUser(['role' => 'receptionist']));
        $this->postJson('/api/v1/admin/settings/test-email')->assertForbidden();
    }

    public function test_test_email_is_rejected_when_mail_is_disabled(): void
    {
        Mail::fake();
        SiteSetting::current()->update(['mail_enabled' => false]);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/settings/test-email')
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }
}

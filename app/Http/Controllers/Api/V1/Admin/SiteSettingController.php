<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SiteTestMail;
use App\Models\SiteSetting;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function __construct(private MailConfigService $mailConfig) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => SiteSetting::current()->toAdminApi(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        $data = $request->validate([
            'siteName' => ['sometimes', 'required', 'string', 'max:160'],
            'logo' => ['nullable', 'string', 'max:255'],
            'websiteUrl' => ['nullable', 'string', 'max:255'],
            'facebookUrl' => ['nullable', 'string', 'max:255'],
            'instagramUrl' => ['nullable', 'string', 'max:255'],
            'twitterUrl' => ['nullable', 'string', 'max:255'],
            'linkedinUrl' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:160'],
            'aboutTitle' => ['nullable', 'string', 'max:160'],
            'aboutShortDesc' => ['nullable', 'string', 'max:2000'],
            'aboutLongDesc' => ['nullable', 'string'],
            'aboutImage' => ['nullable', 'string', 'max:255'],
            'mailEnabled' => ['sometimes', 'boolean'],
            'mailFromName' => ['nullable', 'string', 'max:160'],
            'mailFromAddress' => ['nullable', 'email', 'max:160'],
            'mailHost' => ['nullable', 'string', 'max:255'],
            'mailPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mailEncryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mailUsername' => ['nullable', 'string', 'max:255'],
            'mailPassword' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = SiteSetting::current();
        $settings->update($this->attrs($data));

        return response()->json([
            'data' => $settings->fresh()->toAdminApi(),
        ]);
    }

    public function testEmail(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        $data = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $settings = SiteSetting::current();

        if (! $settings->mail_enabled) {
            return response()->json([
                'message' => 'Outgoing email is disabled. Enable it in email config first.',
            ], 422);
        }

        $to = $data['email']
            ?: $request->user()?->email
            ?: $settings->mail_from_address
            ?: $settings->email;

        if (! $to) {
            return response()->json([
                'message' => 'Enter a recipient email to send a test.',
            ], 422);
        }

        $sent = $this->mailConfig->send(new SiteTestMail($settings->site_name ?: 'Elixir Clinic'), $to);

        if (! $sent) {
            return response()->json([
                'message' => 'Could not send the test email. Check the SMTP host, port, and password.',
            ], 422);
        }

        return response()->json([
            'message' => "Test email sent to {$to}.",
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'kind' => ['nullable', 'in:logo,about'],
        ]);

        $folder = $request->input('kind') === 'about' ? 'settings/about' : 'settings/logo';
        $path = $request->file('file')->store($folder, 'public');

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attrs(array $data): array
    {
        $map = [
            'siteName' => 'site_name',
            'logo' => 'logo',
            'websiteUrl' => 'website_url',
            'facebookUrl' => 'facebook_url',
            'instagramUrl' => 'instagram_url',
            'twitterUrl' => 'twitter_url',
            'linkedinUrl' => 'linkedin_url',
            'phone' => 'phone',
            'whatsapp' => 'whatsapp',
            'email' => 'email',
            'address' => 'address',
            'hours' => 'hours',
            'aboutTitle' => 'about_title',
            'aboutShortDesc' => 'about_short_desc',
            'aboutLongDesc' => 'about_long_desc',
            'aboutImage' => 'about_image',
            'mailEnabled' => 'mail_enabled',
            'mailFromName' => 'mail_from_name',
            'mailFromAddress' => 'mail_from_address',
            'mailHost' => 'mail_host',
            'mailPort' => 'mail_port',
            'mailEncryption' => 'mail_encryption',
            'mailUsername' => 'mail_username',
            'mailPassword' => 'mail_password',
        ];

        $attrs = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $value = $data[$input];
                if ($column === 'mail_password' && ($value === null || $value === '')) {
                    continue;
                }
                $attrs[$column] = $value === '' ? null : $value;
            }
        }

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can update site settings.');
        }
    }
}

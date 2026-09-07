<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => SiteSetting::current()->toApi(),
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
        ]);

        $settings = SiteSetting::current();
        $settings->update($this->attrs($data));

        return response()->json([
            'data' => $settings->fresh()->toApi(),
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
        ];

        $attrs = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $value = $data[$input];
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

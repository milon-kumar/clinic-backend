<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeBlockController extends Controller
{
    public function index(): JsonResponse
    {
        HomeBlock::seedDefaults();

        $blocks = HomeBlock::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $blocks]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $block = HomeBlock::findOrFail($id);
        $data = $this->validated($request);
        $block->update($this->attrs($data));

        return response()->json(['data' => $block->fresh()->toApi()]);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $request->file('file')->store('home', 'public');

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'kicker' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'copy' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'ctaLabel' => ['nullable', 'string', 'max:80'],
            'ctaUrl' => ['nullable', 'string', 'max:255'],
            'cta2Label' => ['nullable', 'string', 'max:80'],
            'cta2Url' => ['nullable', 'string', 'max:255'],
            'layout' => ['nullable', 'string', 'max:40'],
            'items' => ['nullable', 'array'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attrs(array $data): array
    {
        $map = [
            'kicker' => 'kicker',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'copy' => 'copy',
            'image' => 'image',
            'ctaLabel' => 'cta_label',
            'ctaUrl' => 'cta_url',
            'cta2Label' => 'cta2_label',
            'cta2Url' => 'cta2_url',
            'layout' => 'layout',
            'sortOrder' => 'sort_order',
            'isActive' => 'is_active',
        ];

        $attrs = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $value = $data[$input];
                $attrs[$column] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('items', $data)) {
            $attrs['items'] = array_values($data['items'] ?? []);
        }

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can edit the home page.');
        }
    }
}

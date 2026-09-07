<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryLanding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryLandingController extends Controller
{
    public function index(): JsonResponse
    {
        CategoryLanding::seedDefaults();

        $rows = CategoryLanding::query()
            ->orderBy('title')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $data = $this->validated($request);
        $attrs = $this->attrs($data);
        $attrs['is_active'] = $data['isActive'] ?? true;
        if (empty($attrs['category'])) {
            $attrs['category'] = $attrs['title'] ?? $attrs['slug'];
        }

        $landing = CategoryLanding::create($attrs);

        return response()->json(['data' => $landing->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $landing = CategoryLanding::findOrFail($id);
        $data = $this->validated($request, $id);
        $landing->update($this->attrs($data, $id));

        return response()->json(['data' => $landing->fresh()->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        CategoryLanding::findOrFail($id)->delete();

        return response()->json(['message' => 'Category page deleted']);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $request->file('file')->store('landings', 'public');

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null): array
    {
        $creating = $id === null;

        return $request->validate([
            'slug' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'title' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'heroImage' => ['nullable', 'string', 'max:255'],
            'listTitle' => ['nullable', 'string', 'max:160'],
            'listCopy' => ['nullable', 'string', 'max:255'],
            'benefits' => ['nullable', 'array'],
            'benefits.*.icon' => ['nullable', 'string', 'max:80'],
            'benefits.*.title' => ['nullable', 'string', 'max:120'],
            'benefits.*.description' => ['nullable', 'string'],
            'aliases' => ['nullable', 'array'],
            'ctaTitle' => ['nullable', 'string', 'max:160'],
            'ctaCopy' => ['nullable', 'string'],
            'ctaUrl' => ['nullable', 'string', 'max:255'],
            'isActive' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attrs(array $data, ?int $id = null): array
    {
        $map = [
            'category' => 'category',
            'title' => 'title',
            'description' => 'description',
            'heroImage' => 'hero_image',
            'listTitle' => 'list_title',
            'listCopy' => 'list_copy',
            'ctaTitle' => 'cta_title',
            'ctaCopy' => 'cta_copy',
            'ctaUrl' => 'cta_url',
            'isActive' => 'is_active',
        ];

        if (array_key_exists('slug', $data) || $id === null) {
            $slug = CategoryLanding::normalizeSlug($data['slug'] ?? null, $data['title'] ?? null);
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'slug' => 'Enter a URL slug such as coolsculpting.',
                ]);
            }
            if (in_array($slug, CategoryLanding::reservedSlugs(), true)) {
                throw ValidationException::withMessages([
                    'slug' => 'That URL is reserved by the site.',
                ]);
            }
            $unique = Rule::unique('category_landings', 'slug');
            if ($id !== null) {
                $unique = $unique->ignore($id);
            }
            validator(['slug' => $slug], ['slug' => [$unique]])->validate();
            $data['slug'] = $slug;
            $map = ['slug' => 'slug'] + $map;
        }

        $attrs = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $value = $data[$input];
                $attrs[$column] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('benefits', $data)) {
            $clean = [];
            foreach ($data['benefits'] ?? [] as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $clean[] = [
                    'icon' => $row['icon'] ?? 'bi-stars',
                    'title' => $title,
                    'description' => $row['description'] ?? null,
                ];
            }
            $attrs['benefits'] = $clean;
        }

        if (array_key_exists('aliases', $data)) {
            $attrs['aliases'] = array_values(array_filter($data['aliases'] ?? []));
        }

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can edit category pages.');
        }
    }
}

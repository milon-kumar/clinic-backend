<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MotherCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotherCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = MotherCategory::query()
            ->orderBy('sort_order')
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
        $row = MotherCategory::create($this->attrs($data));

        return response()->json(['data' => $row->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $row = MotherCategory::findOrFail($id);
        $row->update($this->attrs($this->validated($request, $id), $id));

        return response()->json(['data' => $row->fresh()->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        MotherCategory::findOrFail($id)->delete();

        return response()->json(['message' => 'Treatment type deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null): array
    {
        $creating = $id === null;

        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:160'],
            'slug' => [$creating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:80'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
            'showInMenu' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attrs(array $data, ?int $id = null): array
    {
        $map = [
            'title' => 'title',
            'sortOrder' => 'sort_order',
            'isActive' => 'is_active',
            'showInMenu' => 'show_in_menu',
        ];

        if (array_key_exists('slug', $data) || $id === null) {
            $slug = MotherCategory::normalizeSlug($data['slug'] ?? null, $data['title'] ?? null);
            if ($slug === '') {
                throw ValidationException::withMessages([
                    'slug' => 'Enter a slug such as aesthetic-treatments.',
                ]);
            }
            $unique = Rule::unique('mother_categories', 'slug');
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
                $attrs[$column] = $data[$input];
            }
        }

        if ($id === null) {
            $attrs['is_active'] = $data['isActive'] ?? true;
            $attrs['show_in_menu'] = $data['showInMenu'] ?? false;
            $attrs['sort_order'] = $data['sortOrder'] ?? 0;
        }

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can edit treatment types.');
        }
    }
}

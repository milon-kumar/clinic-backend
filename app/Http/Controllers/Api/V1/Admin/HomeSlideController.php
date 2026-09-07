<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSlide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeSlideController extends Controller
{
    public function index(): JsonResponse
    {
        HomeSlide::seedDefaults();

        $slides = HomeSlide::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $slides]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $data = $this->validated($request);

        $slide = HomeSlide::create($this->attrs($data) + [
            'sort_order' => $data['sortOrder'] ?? ((int) HomeSlide::query()->max('sort_order') + 1),
            'is_active' => $data['isActive'] ?? true,
            'interval_ms' => $data['intervalMs'] ?? 5000,
        ]);

        return response()->json(['data' => $slide->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $slide = HomeSlide::findOrFail($id);
        $data = $this->validated($request, updating: true);
        $slide->update($this->attrs($data));

        return response()->json(['data' => $slide->fresh()->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        HomeSlide::findOrFail($id)->delete();

        return response()->json(['message' => 'Slide deleted']);
    }

    public function upload(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $request->file('file')->store('slides', 'public');

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $image = $updating ? ['sometimes', 'required', 'string', 'max:255'] : ['required', 'string', 'max:255'];

        return $request->validate([
            'image' => $image,
            'title' => ['nullable', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'linkUrl' => ['nullable', 'string', 'max:255'],
            'intervalMs' => ['nullable', 'integer', 'min:1000', 'max:60000'],
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
            'image' => 'image',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'linkUrl' => 'link_url',
            'intervalMs' => 'interval_ms',
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

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can manage home slides.');
        }
    }
}

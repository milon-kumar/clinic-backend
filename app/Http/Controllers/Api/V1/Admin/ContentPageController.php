<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContentPageController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ContentPage::query()->orderBy('title')->get()->map(fn (ContentPage $p) => $p->toApi())->values();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $page = ContentPage::create($this->attrs($this->validated($request)));

        return response()->json(['data' => $page->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $page = ContentPage::findOrFail($id);
        $page->update($this->attrs($this->validated($request, true), $id));

        return response()->json(['data' => $page->fresh()->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        ContentPage::findOrFail($id)->delete();

        return response()->json(['message' => 'Page deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120'],
            'content' => ['nullable', 'string', 'max:200000'],
            'metaTitle' => ['nullable', 'string', 'max:160'],
            'metaDescription' => ['nullable', 'string', 'max:320'],
            'isPublished' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attrs(array $data, ?int $id = null): array
    {
        $attrs = [];

        if (array_key_exists('title', $data)) {
            $attrs['title'] = $data['title'];
        }
        if (array_key_exists('content', $data)) {
            $attrs['content'] = ContentPage::sanitize($data['content']);
        }
        foreach (['metaTitle' => 'meta_title', 'metaDescription' => 'meta_description'] as $in => $col) {
            if (array_key_exists($in, $data)) {
                $attrs[$col] = ($data[$in] ?? '') === '' ? null : $data[$in];
            }
        }
        if (array_key_exists('isPublished', $data)) {
            $attrs['is_published'] = (bool) $data['isPublished'];
        }

        if ($id === null || array_key_exists('slug', $data)) {
            $slug = ContentPage::normalizeSlug($data['slug'] ?? null, $data['title'] ?? null);
            if ($slug === '' || in_array($slug, ContentPage::RESERVED_SLUGS, true)) {
                throw ValidationException::withMessages(['slug' => ['Enter a valid page URL such as terms-and-conditions.']]);
            }
            $unique = Rule::unique('content_pages', 'slug');
            if ($id !== null) {
                $unique = $unique->ignore($id);
            }
            validator(['slug' => $slug], ['slug' => [$unique]])->validate();
            $attrs['slug'] = $slug;
        }

        return $attrs;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only an organisation admin can edit pages.');
        }
    }
}

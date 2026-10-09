<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\JsonResponse;

class ContentPageController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ContentPage::query()
            ->where('is_published', true)
            ->orderBy('title')
            ->get()
            ->map(fn (ContentPage $p) => $p->toApi(false))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = ContentPage::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()->json(['data' => $page->toApi()]);
    }
}

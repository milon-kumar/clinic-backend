<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CategoryLanding;
use Illuminate\Http\JsonResponse;

class CategoryLandingController extends Controller
{
    public function index(): JsonResponse
    {
        CategoryLanding::seedDefaults();

        $rows = CategoryLanding::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function show(string $slug): JsonResponse
    {
        CategoryLanding::seedDefaults();

        $landing = CategoryLanding::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json(['data' => $landing->toApi()]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CategoryLanding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryLandingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        CategoryLanding::seedDefaults();

        $query = CategoryLanding::query()
            ->where('is_active', true)
            ->orderBy('title');

        if ($request->boolean('menu')) {
            $query->where('show_in_menu', true);
        }

        $rows = $query->get()->map->toApi()->values();

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

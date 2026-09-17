<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MotherCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotherCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MotherCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($request->boolean('menu')) {
            $query->where('show_in_menu', true);
        }

        $rows = $query->get()->map->toApi()->values();

        return response()->json(['data' => $rows]);
    }
}

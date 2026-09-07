<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HomeSlide;
use Illuminate\Http\JsonResponse;

class HomeSlideController extends Controller
{
    public function index(): JsonResponse
    {
        HomeSlide::seedDefaults();

        $slides = HomeSlide::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $slides]);
    }
}

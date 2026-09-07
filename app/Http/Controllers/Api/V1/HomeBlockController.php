<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HomeBlock;
use Illuminate\Http\JsonResponse;

class HomeBlockController extends Controller
{
    public function index(): JsonResponse
    {
        HomeBlock::seedDefaults();

        $blocks = HomeBlock::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'why' => $blocks->firstWhere('slot', 'why')?->toApi(),
                'concerns' => $blocks->firstWhere('slot', 'concerns')?->toApi(),
                'finder' => $blocks->firstWhere('slot', 'finder')?->toApi(),
                'journey' => $blocks->firstWhere('slot', 'journey')?->toApi(),
                'promos' => $blocks->where('slot', 'promo')->values()->map->toApi()->all(),
            ],
        ]);
    }
}

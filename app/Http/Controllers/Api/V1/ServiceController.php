<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Service::query()
            ->where('is_active', true)
            ->with(['packages', 'benefits', 'faqs']);

        if ($category = $request->query('category')) {
            $needle = mb_strtolower($category);
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(category) = ?', [$needle])
                    ->orWhereRaw('LOWER(treatment_type) = ?', [$needle]);
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($type = $request->query('treatmentType', $request->query('treatment_type'))) {
            $query->whereRaw('LOWER(treatment_type) = ?', [mb_strtolower($type)]);
        }

        if ($search = $request->query('q', $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('name')->get()->map->toApi()->values();

        return response()->json(['data' => $services]);
    }

    public function show(int $id): JsonResponse
    {
        $service = Service::query()
            ->with(['packages', 'benefits', 'faqs'])
            ->findOrFail($id);

        $data = $service->toApi();
        $data['recommended'] = $service->recommendedServices()
            ->map(fn (Service $row) => $row->toApi())
            ->all();

        return response()->json(['data' => $data]);
    }
}

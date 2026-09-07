<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ClinicCatalogService;
use App\Services\PrerequisiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private ClinicCatalogService $catalogService,
        private PrerequisiteService $prerequisiteService,
    ) {}

    public function services(Request $request, int $id): JsonResponse
    {
        $mode = $request->query('mode', 'all');
        $offerings = $this->catalogService->getServices($id, $mode)->map(function (array $offering) {
            $offering['packages'] = collect($offering['packages'])->map(function ($pkg) {
                return method_exists($pkg, 'toApi') ? $pkg->toApi() : $pkg;
            })->all();
            $offering['benefits'] = collect($offering['benefits'])->map(function ($item) {
                return method_exists($item, 'toApi') ? $item->toApi() : $item;
            })->all();
            $offering['faqs'] = collect($offering['faqs'])->map(function ($item) {
                return method_exists($item, 'toApi') ? $item->toApi() : $item;
            })->all();
            $offering['id'] = $offering['serviceId'];
            $offering['title'] = $offering['name'];
            $offering['price'] = ($offering['pricePence'] ?? 0) / 100;

            return $offering;
        });

        return response()->json(['data' => $offerings]);
    }

    public function serviceBySlug(int $clinicId, string $slug): JsonResponse
    {
        $service = Service::query()->where('slug', $slug)->firstOrFail();
        $offerings = $this->catalogService->getServices($clinicId, 'all');
        $match = $offerings->firstWhere('slug', $slug);

        if (! $match) {
            abort(404, 'Service not offered at this clinic');
        }

        $match['packages'] = collect($match['packages'])->map(fn ($p) => method_exists($p, 'toApi') ? $p->toApi() : $p)->all();
        $match['benefits'] = collect($match['benefits'])->map(fn ($p) => method_exists($p, 'toApi') ? $p->toApi() : $p)->all();
        $match['faqs'] = collect($match['faqs'])->map(fn ($p) => method_exists($p, 'toApi') ? $p->toApi() : $p)->all();
        $match['id'] = $service->id;
        $match['title'] = $match['name'];
        $match['images'] = $service->images ?? [];
        $match['price'] = ($match['pricePence'] ?? 0) / 100;

        return response()->json(['data' => $match]);
    }

    public function prerequisites(int $serviceId): JsonResponse
    {
        return response()->json([
            'data' => $this->prerequisiteService->getPrerequisiteTree($serviceId),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Clinic;
use App\Services\ClinicCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function __construct(private ClinicCatalogService $catalogService) {}
    public function index(Request $request): JsonResponse
    {
        $query = Clinic::query()->where('is_active', true)->with('schedules');

        if ($search = $request->query('q', $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($region = $request->query('region')) {
            $query->where('region', $region);
        }

        $clinics = $query->orderBy('name')->get()->map->toApi()->values();

        return response()->json(['data' => $clinics]);
    }

    public function show(int $id): JsonResponse
    {
        $clinic = Clinic::query()->with('schedules')->findOrFail($id);

        return response()->json(['data' => $clinic->toApi()]);
    }

    public function setSessionClinic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
        ]);

        $user = $request->user();
        $user->update(['selected_clinic_id' => $data['clinicId']]);

        foreach (['buy', 'book'] as $type) {
            $cart = Cart::query()
                ->where('customer_id', $user->id)
                ->where('cart_type', $type)
                ->first();

            if ($cart) {
                $this->catalogService->dropUnavailableLines($cart, (int) $data['clinicId']);
                $cart->update(['clinic_id' => $data['clinicId']]);
            }
        }

        $clinic = Clinic::with('schedules')->findOrFail($data['clinicId']);

        return response()->json([
            'data' => [
                'clinicId' => $clinic->id,
                'clinic' => $clinic->toApi(),
                'user' => $user->fresh()->toApi(),
            ],
        ]);
    }
}

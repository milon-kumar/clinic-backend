<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\Service;
use App\Services\CartPricingEngine;
use App\Services\ClinicCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        private CartPricingEngine $pricingEngine,
        private ClinicCatalogService $catalogService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request, $request->query('type', 'buy'));

        return response()->json(['data' => $this->pricingEngine->priceCart($cart, $request->user())]);
    }

    public function addLine(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
            'type' => ['nullable', 'in:buy,book'],
        ]);

        $type = $data['type'] ?? 'buy';
        $user = $request->user();
        $cart = $this->resolveCart($request, $type);
        $clinicId = $data['clinicId'] ?? $user->selected_clinic_id ?? $cart->clinic_id;

        if (! $clinicId) {
            throw ValidationException::withMessages([
                'clinicId' => ['Select a clinic first. Carts are branch-specific and treatments cannot be transferred.'],
            ]);
        }

        $availability = $this->catalogService->isAvailable((int) $clinicId, $data['serviceId'], $type);
        if (! $availability['ok']) {
            throw ValidationException::withMessages([
                'serviceId' => [$availability['reason'] ?? 'Not available at this clinic'],
            ]);
        }

        if ($cart->clinic_id && (int) $cart->clinic_id !== (int) $clinicId) {
            $this->catalogService->dropUnavailableLines($cart, (int) $clinicId);
        }

        $cart->update(['clinic_id' => $clinicId]);
        $user->update(['selected_clinic_id' => $clinicId]);

        $service = Service::findOrFail($data['serviceId']);
        $unit = $this->catalogService->resolvePrice((int) $clinicId, $service->id, 1)['unitPricePence'];

        $existing = $cart->lines()->where('service_id', $service->id)->first();
        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + $data['quantity'],
                'unit_price_pence' => $unit,
            ]);
        } else {
            CartLine::create([
                'cart_id' => $cart->id,
                'service_id' => $service->id,
                'quantity' => $data['quantity'],
                'unit_price_pence' => $unit,
            ]);
        }

        $cart->refresh();

        return response()->json(['data' => $this->pricingEngine->priceCart($cart, $user)]);
    }

    public function updateClinic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'type' => ['nullable', 'in:buy,book'],
        ]);

        $cart = $this->resolveCart($request, $data['type'] ?? 'buy');
        $this->catalogService->dropUnavailableLines($cart, (int) $data['clinicId']);
        $cart->update(['clinic_id' => $data['clinicId']]);
        $request->user()->update(['selected_clinic_id' => $data['clinicId']]);
        $cart->refresh();

        return response()->json(['data' => $this->pricingEngine->priceCart($cart, $request->user())]);
    }

    public function applyPromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'type' => ['nullable', 'in:buy,book'],
        ]);

        $cart = $this->resolveCart($request, $data['type'] ?? 'buy');
        $cart->update(['promo_code' => strtoupper($data['code'])]);
        $cart->refresh();

        $priced = $this->pricingEngine->priceCart($cart, $request->user());

        return response()->json(['data' => $priced]);
    }

    public function updateLine(Request $request, int $lineId): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'type' => ['nullable', 'in:buy,book'],
        ]);

        $cart = $this->resolveCart($request, $data['type'] ?? 'buy');
        $line = $cart->lines()->where('id', $lineId)->first();

        if (! $line) {
            throw ValidationException::withMessages([
                'lineId' => ['Cart item not found.'],
            ]);
        }

        $unit = $cart->clinic_id
            ? $this->catalogService->resolvePrice((int) $cart->clinic_id, $line->service_id, 1)['unitPricePence']
            : $line->unit_price_pence;

        $line->update([
            'quantity' => $data['quantity'],
            'unit_price_pence' => $unit,
        ]);

        $cart->refresh();

        return response()->json(['data' => $this->pricingEngine->priceCart($cart, $request->user())]);
    }

    public function removeLine(Request $request, int $lineId): JsonResponse
    {
        $cart = $this->resolveCart($request, $request->query('type', 'buy'));
        $cart->lines()->where('id', $lineId)->delete();
        $cart->refresh();

        return response()->json(['data' => $this->pricingEngine->priceCart($cart, $request->user())]);
    }

    private function resolveCart(Request $request, string $type): Cart
    {
        $user = $request->user();

        return Cart::firstOrCreate(
            [
                'customer_id' => $user->id,
                'cart_type' => $type,
            ],
            [
                'clinic_id' => $user->selected_clinic_id,
            ]
        );
    }
}

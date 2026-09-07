<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentSession;
use App\Services\CartPricingEngine;
use App\Services\ClinicCatalogService;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyController extends Controller
{
    public function __construct(
        private CartPricingEngine $pricingEngine,
        private PackageService $packageService,
        private ClinicCatalogService $catalogService,
        private InventoryService $inventoryService,
        private InvoiceService $invoiceService,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $cart = $this->buyCart($request);
        $priced = $this->pricingEngine->priceCart($cart, $request->user());

        return response()->json([
            'data' => [
                'step' => 'start',
                'cart' => $priced,
            ],
        ]);
    }

    public function clinic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
        ]);

        $cart = $this->buyCart($request);
        $this->catalogService->dropUnavailableLines($cart, (int) $data['clinicId']);
        $cart->update(['clinic_id' => $data['clinicId']]);
        $request->user()->update(['selected_clinic_id' => $data['clinicId']]);
        $cart->refresh();

        return response()->json([
            'data' => [
                'step' => 'clinic',
                'cart' => $this->pricingEngine->priceCart($cart, $request->user()),
            ],
        ]);
    }

    public function finalise(Request $request): JsonResponse
    {
        $cart = $this->buyCart($request);
        $priced = $this->assertCheckoutable($cart, $request);

        return response()->json([
            'data' => [
                'step' => 'finalise',
                'cart' => $priced,
            ],
        ]);
    }

    public function paymentIntent(Request $request): JsonResponse
    {
        $cart = $this->buyCart($request);
        $priced = $this->assertCheckoutable($cart, $request);
        $session = $this->createPaymentSession($request, $cart, $priced);

        return response()->json([
            'data' => [
                'sessionId' => $session->id,
                'url' => $this->checkoutUrl($session->id),
                'amountPence' => $session->amount_pence,
            ],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sessionId' => ['nullable', 'string'],
        ]);

        $cart = $this->buyCart($request);

        if (! empty($data['sessionId'])) {
            $session = PaymentSession::findOrFail($data['sessionId']);
            if ($session->customer_id !== $request->user()->id) {
                abort(403);
            }
            $session->update(['status' => 'paid']);
        }

        $order = $this->createPaidOrder($request, $cart);
        $packages = $this->packageService->createFromOrder($order);
        $cart->lines()->delete();
        $cart->update(['promo_code' => null]);

        return response()->json([
            'data' => [
                'orderId' => $order->id,
                'clinicId' => $order->clinic_id,
                'packages' => $packages->map->toApi()->values(),
            ],
        ]);
    }

    private function buyCart(Request $request): Cart
    {
        return Cart::firstOrCreate(
            [
                'customer_id' => $request->user()->id,
                'cart_type' => 'buy',
            ],
            [
                'clinic_id' => $request->user()->selected_clinic_id,
            ]
        );
    }

    private function assertCheckoutable(Cart $cart, Request $request): array
    {
        $priced = $this->pricingEngine->priceCart($cart, $request->user());

        if (! $priced['summary']['canCheckout']) {
            throw ValidationException::withMessages([
                'cart' => [$priced['summary']['checkoutBlockReason'] ?? 'Checkout is blocked'],
            ]);
        }

        return $priced;
    }

    private function createPaymentSession(Request $request, Cart $cart, array $priced): PaymentSession
    {
        return PaymentSession::create([
            'id' => 'cs_'.Str::uuid(),
            'customer_id' => $request->user()->id,
            'purpose' => 'buy',
            'payload' => [
                'cartId' => $cart->id,
                'clinicId' => $cart->clinic_id,
            ],
            'status' => 'pending',
            'amount_pence' => $priced['summary']['totalPence'],
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function checkoutUrl(string $sessionId): string
    {
        $front = rtrim(config('app.frontend_url'), '/');

        return $front.'/payment/success?session_id='.$sessionId;
    }

    private function createPaidOrder(Request $request, Cart $cart): Order
    {
        $priced = $this->assertCheckoutable($cart, $request);

        return DB::transaction(function () use ($request, $cart, $priced) {
            $order = Order::create([
                'customer_id' => $request->user()->id,
                'clinic_id' => $cart->clinic_id,
                'status' => 'paid',
                'subtotal_pence' => $priced['summary']['subtotalPence'],
                'discount_pence' => $priced['summary']['discountPence'],
                'total_pence' => $priced['summary']['totalPence'],
                'payment_method' => 'card',
                'paid_at' => now(),
            ]);

            foreach ($cart->lines as $line) {
                OrderLine::create([
                    'order_id' => $order->id,
                    'service_id' => $line->service_id,
                    'quantity' => $line->quantity,
                    'unit_price_pence' => $line->unit_price_pence,
                ]);
            }

            $order->load('lines.service');
            $this->inventoryService->consumeForOrder($order, $request->user());
            $this->invoiceService->issueForOrder($order);

            return $order->load('lines.service');
        });
    }
}

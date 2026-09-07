<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentSession;
use App\Models\Service;
use App\Models\SlotHold;
use App\Services\CartPricingEngine;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\PackageService;
use App\Services\SlotHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private CartPricingEngine $pricingEngine,
        private PackageService $packageService,
        private SlotHoldService $slotHoldService,
        private InventoryService $inventoryService,
        private InvoiceService $invoiceService,
    ) {}

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['nullable'],
            'purpose' => ['nullable', 'in:buy,book'],
            'holdId' => ['nullable', 'string'],
            'clinicId' => ['nullable', 'integer'],
            'serviceId' => ['nullable', 'integer'],
            'fullName' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'appointmentDate' => ['nullable'],
            'appointmentTime' => ['nullable', 'string'],
            'treatmentId' => ['nullable'],
            'packageId' => ['nullable'],
            'price' => ['nullable', 'numeric'],
        ]);

        $purpose = $data['purpose'] ?? (isset($data['holdId']) || isset($data['appointmentDate']) ? 'book' : 'buy');
        $user = $request->user();

        if ($purpose === 'buy') {
            $cart = Cart::firstOrCreate(
                ['customer_id' => $user->id, 'cart_type' => 'buy'],
                ['clinic_id' => $user->selected_clinic_id]
            );
            $priced = $this->pricingEngine->priceCart($cart, $user);
            if (! $priced['summary']['canCheckout']) {
                throw ValidationException::withMessages([
                    'cart' => [$priced['summary']['checkoutBlockReason'] ?? 'Checkout is blocked'],
                ]);
            }
            $amount = $priced['summary']['totalPence'];
            $payload = ['cartId' => $cart->id, 'clinicId' => $cart->clinic_id];
        } else {
            $serviceId = $data['serviceId'] ?? $data['treatmentId'] ?? null;
            if (! $serviceId && ! empty($data['holdId'])) {
                $serviceId = SlotHold::query()->whereKey($data['holdId'])->value('service_id');
            }
            $service = $serviceId ? Service::find($serviceId) : null;
            $amount = $service?->appointmentAmountPence() ?? 0;
            if ($amount === 0) {
                throw ValidationException::withMessages([
                    'price' => ['This appointment is free and does not need online payment.'],
                ]);
            }
            $payload = [
                'holdId' => $data['holdId'] ?? null,
                'clinicId' => $data['clinicId'] ?? $user->selected_clinic_id,
                'serviceId' => $serviceId,
                'fullName' => $data['fullName'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
                'notes' => $data['notes'] ?? null,
                'appointmentDate' => $data['appointmentDate'] ?? null,
                'appointmentTime' => $data['appointmentTime'] ?? null,
                'items' => $data['items'] ?? null,
                'amountPence' => $amount,
            ];
        }

        $session = PaymentSession::create([
            'id' => 'cs_'.Str::uuid(),
            'customer_id' => $user->id,
            'purpose' => $purpose,
            'payload' => $payload,
            'status' => 'pending',
            'amount_pence' => $amount,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json([
            'sessionId' => $session->id,
            'url' => rtrim(config('app.frontend_url'), '/').'/payment/success?session_id='.$session->id,
            'amountPence' => $amount,
        ]);
    }

    public function session(Request $request, string $sessionId): JsonResponse
    {
        $session = PaymentSession::findOrFail($sessionId);

        if ($session->customer_id !== $request->user()->id) {
            abort(403);
        }

        if ($session->status === 'pending') {
            $session = $this->fulfill($session, $request);
        }

        return response()->json([
            'sessionId' => $session->id,
            'status' => $session->status,
            'purpose' => $session->purpose,
            'appointmentId' => $session->payload['appointmentId'] ?? null,
            'orderId' => $session->payload['orderId'] ?? null,
            'amountPence' => $session->amount_pence,
        ]);
    }

    private function fulfill(PaymentSession $session, Request $request): PaymentSession
    {
        return DB::transaction(function () use ($session, $request) {
            $payload = $session->payload ?? [];

            if ($session->purpose === 'buy') {
                $cart = Cart::findOrFail($payload['cartId']);
                $priced = $this->pricingEngine->priceCart($cart, $request->user());

                $order = Order::create([
                    'customer_id' => $request->user()->id,
                    'clinic_id' => $cart->clinic_id,
                    'status' => 'paid',
                    'subtotal_pence' => $priced['summary']['subtotalPence'],
                    'discount_pence' => $priced['summary']['discountPence'],
                    'total_pence' => $priced['summary']['totalPence'],
                    'payment_method' => 'card',
                    'stripe_session_id' => $session->id,
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

                $this->packageService->createFromOrder($order->load('lines.service'));
                $this->inventoryService->consumeForOrder($order, $request->user());
                $this->invoiceService->issueForOrder($order);
                $cart->lines()->delete();
                $cart->update(['promo_code' => null]);

                $payload['orderId'] = $order->id;
            } else {
                $appointment = null;

                $amountPence = (int) ($payload['amountPence'] ?? $session->amount_pence ?? 0);
                if (! empty($payload['holdId'])) {
                    $appointment = $this->slotHoldService->confirmHold($payload['holdId'], [
                        'customer_id' => $request->user()->id,
                        'full_name' => $payload['fullName'] ?? $request->user()->name,
                        'phone' => $payload['phone'] ?? $request->user()->phone,
                        'email' => $payload['email'] ?? $request->user()->email,
                        'notes' => $payload['notes'] ?? null,
                        'amount_pence' => $amountPence,
                        'payment_method' => 'online',
                        'payment_status' => 'paid',
                        'status' => 'confirmed',
                    ]);
                } elseif (! empty($payload['serviceId']) && ! empty($payload['appointmentDate'])) {
                    $appointment = Appointment::create([
                        'customer_id' => $request->user()->id,
                        'clinic_id' => $payload['clinicId'] ?? $request->user()->selected_clinic_id,
                        'service_id' => $payload['serviceId'],
                        'full_name' => $payload['fullName'] ?? $request->user()->name,
                        'phone' => $payload['phone'] ?? $request->user()->phone,
                        'email' => $payload['email'] ?? $request->user()->email,
                        'notes' => $payload['notes'] ?? null,
                        'appointment_date' => Carbon::parse($payload['appointmentDate'])->toDateString(),
                        'appointment_time' => $payload['appointmentTime'] ?? '10:00 AM',
                        'status' => 'confirmed',
                        'amount_pence' => $amountPence,
                        'payment_method' => 'online',
                        'payment_status' => 'paid',
                        'qr_token' => (string) Str::uuid(),
                    ]);
                }

                if ($appointment) {
                    $payload['appointmentId'] = $appointment->id;
                }
            }

            $session->update([
                'status' => 'paid',
                'payload' => $payload,
            ]);

            return $session->fresh();
        });
    }
}

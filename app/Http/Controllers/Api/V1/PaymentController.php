<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\PaymentSession;
use App\Models\Service;
use App\Models\SlotHold;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentFulfillmentService;
use App\Services\StripeCheckoutService;
use App\Services\StripeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentCheckoutService $paymentCheckout,
        private PaymentFulfillmentService $fulfillment,
        private StripeCheckoutService $stripe,
        private StripeConfigService $stripeConfig,
    ) {}

    public function config(): JsonResponse
    {
        $publishable = $this->stripeConfig->publishableKey();

        return response()->json([
            'data' => [
                'stripeEnabled' => $this->stripeConfig->enabled(),
                'mode' => $this->stripeConfig->mode(),
                'publishableKey' => filled($publishable) ? $publishable : null,
            ],
        ]);
    }

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
            $checkout = $this->paymentCheckout->startBuy($user, $cart);
        } else {
            $serviceId = $data['serviceId'] ?? $data['treatmentId'] ?? null;
            if (! $serviceId && ! empty($data['holdId'])) {
                $serviceId = SlotHold::query()->whereKey($data['holdId'])->value('service_id');
            }
            $service = $serviceId ? Service::find($serviceId) : null;
            $amount = $service?->appointmentAmountPence() ?? 0;
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
            $checkout = $this->paymentCheckout->startBook($user, $amount, $payload, $service);
        }

        $session = $checkout['session'];

        return response()->json([
            'sessionId' => $session->id,
            'url' => $checkout['url'],
            'amountPence' => $session->amount_pence,
        ]);
    }

    public function session(Request $request, string $sessionId): JsonResponse
    {
        $session = PaymentSession::findOrFail($sessionId);

        if ($session->customer_id !== $request->user()->id) {
            abort(403);
        }

        if ($session->status === 'pending') {
            $this->paymentCheckout->assertStripePaid($session);
            $result = $this->fulfillment->fulfillIfPending($session, $request->user());
            $session = $result['session'];
            if ($result['fulfilled']) {
                $this->fulfillment->notify($session);
            }
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

}

<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentFulfillmentService
{
    public function __construct(
        private CartPricingEngine $pricingEngine,
        private PackageService $packageService,
        private SlotHoldService $slotHoldService,
        private InventoryService $inventoryService,
        private InvoiceService $invoiceService,
        private ClientNotifyService $notify,
    ) {}

    /**
     * @return array{session: PaymentSession, fulfilled: bool}
     */
    public function fulfillIfPending(PaymentSession $session, User $user): array
    {
        return DB::transaction(function () use ($session, $user) {
            $locked = PaymentSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                return ['session' => $locked, 'fulfilled' => false];
            }

            return [
                'session' => $this->fulfill($locked, $user),
                'fulfilled' => true,
            ];
        });
    }

    public function notify(PaymentSession $session): void
    {
        $payload = $session->payload ?? [];

        if (! empty($payload['orderId'])) {
            $order = Order::query()
                ->with(['customer', 'clinic', 'lines.service', 'packages.service', 'packages.clinic'])
                ->find($payload['orderId']);
            if ($order) {
                $this->notify->purchaseConfirmed($order);
            }
        }

        if (! empty($payload['appointmentId'])) {
            $appointment = Appointment::query()
                ->with(['clinic', 'service', 'customer'])
                ->find($payload['appointmentId']);
            if ($appointment) {
                $this->notify->bookingConfirmed($appointment);
            }
        }
    }

    private function fulfill(PaymentSession $session, User $user): PaymentSession
    {
        $payload = $session->payload ?? [];

        if ($session->purpose === 'buy') {
            $cart = Cart::findOrFail($payload['cartId']);
            $priced = $this->pricingEngine->priceCart($cart, $user);

            $order = Order::create([
                'customer_id' => $user->id,
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
            $this->inventoryService->consumeForOrder($order, $user);
            $this->invoiceService->issueForOrder($order);
            $cart->lines()->delete();
            $cart->update(['promo_code' => null]);

            $payload['orderId'] = $order->id;
        } else {
            $appointment = null;

            $amountPence = (int) ($payload['amountPence'] ?? $session->amount_pence ?? 0);
            if (! empty($payload['holdId'])) {
                $appointment = $this->slotHoldService->confirmHold($payload['holdId'], [
                    'customer_id' => $user->id,
                    'full_name' => $payload['fullName'] ?? $user->name,
                    'phone' => $payload['phone'] ?? $user->phone,
                    'email' => $payload['email'] ?? $user->email,
                    'notes' => $payload['notes'] ?? null,
                    'amount_pence' => $amountPence,
                    'payment_method' => 'online',
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                ]);
            } elseif (! empty($payload['serviceId']) && ! empty($payload['appointmentDate'])) {
                $appointment = Appointment::create([
                    'customer_id' => $user->id,
                    'clinic_id' => $payload['clinicId'] ?? $user->selected_clinic_id,
                    'service_id' => $payload['serviceId'],
                    'full_name' => $payload['fullName'] ?? $user->name,
                    'phone' => $payload['phone'] ?? $user->phone,
                    'email' => $payload['email'] ?? $user->email,
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
    }
}

<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\PaymentSession;
use App\Models\Service;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PaymentCheckoutService
{
    public function __construct(
        private CartPricingEngine $pricingEngine,
        private StripeCheckoutService $stripe,
        private StripeConfigService $stripeConfig,
    ) {}

    public function assertOnlinePaymentsAvailable(): void
    {
        if ($this->stripe->configured()) {
            return;
        }

        throw ValidationException::withMessages([
            'payment' => [$this->stripeConfig->unavailableMessage()],
        ]);
    }

    /**
     * @return array{session: PaymentSession, url: string}
     */
    public function startBuy(User $user, Cart $cart): array
    {
        $this->assertOnlinePaymentsAvailable();

        $priced = $this->pricingEngine->priceCart($cart, $user);
        if (! $priced['summary']['canCheckout']) {
            throw ValidationException::withMessages([
                'cart' => [$priced['summary']['checkoutBlockReason'] ?? 'Checkout is blocked'],
            ]);
        }

        $lineItems = [];
        foreach ($priced['lines'] as $line) {
            $lineItems[] = [
                'name' => $line['name'],
                'amountPence' => (int) $line['lineTotalPence'],
                'quantity' => 1,
            ];
        }

        $amount = (int) $priced['summary']['totalPence'];
        $payload = [
            'cartId' => $cart->id,
            'clinicId' => $cart->clinic_id,
        ];

        return $this->createSession($user, 'buy', $amount, $payload, $lineItems);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{session: PaymentSession, url: string}
     */
    public function startBook(User $user, int $amountPence, array $payload, ?Service $service = null): array
    {
        $this->assertOnlinePaymentsAvailable();

        if ($amountPence <= 0) {
            throw ValidationException::withMessages([
                'price' => ['This appointment is free and does not need online payment.'],
            ]);
        }

        $lineItems = [[
            'name' => ($service?->name ?? 'Appointment').' booking',
            'amountPence' => $amountPence,
            'quantity' => 1,
        ]];

        return $this->createSession($user, 'book', $amountPence, $payload, $lineItems);
    }

    public function assertStripePaid(PaymentSession $session): void
    {
        if ($session->status === 'paid') {
            return;
        }

        if (str_starts_with($session->id, 'cs_local_') || ! $this->stripe->configured()) {
            return;
        }

        if (! str_starts_with($session->id, 'cs_')) {
            throw ValidationException::withMessages([
                'session' => ['Invalid payment session.'],
            ]);
        }

        $stripeSession = $this->stripe->retrieve($session->id);
        if (! $this->stripe->isPaid($stripeSession)) {
            throw ValidationException::withMessages([
                'session' => ['Payment has not been completed yet.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{name: string, amountPence: int, quantity?: int}>  $lineItems
     * @return array{session: PaymentSession, url: string}
     */
    private function createSession(
        User $user,
        string $purpose,
        int $amountPence,
        array $payload,
        array $lineItems,
    ): array {
        $this->assertOnlinePaymentsAvailable();

        $stripeSession = $this->stripe->createCheckoutSession(
            $lineItems,
            $purpose,
            (int) $user->id,
            $user->email,
        );

        $session = PaymentSession::create([
            'id' => $stripeSession->id,
            'customer_id' => $user->id,
            'purpose' => $purpose,
            'payload' => $payload,
            'status' => 'pending',
            'amount_pence' => $amountPence,
            'expires_at' => now()->addMinutes(30),
        ]);

        return [
            'session' => $session,
            'url' => $stripeSession->url,
        ];
    }
}

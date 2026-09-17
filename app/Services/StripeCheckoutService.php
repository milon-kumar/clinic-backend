<?php

namespace App\Services;

use Stripe\Checkout\Session;
use Stripe\Stripe;

class StripeCheckoutService
{
    public function __construct(
        private StripeConfigService $stripeConfig,
    ) {}

    public function configured(): bool
    {
        return $this->stripeConfig->enabled();
    }

    public function frontendUrl(string $path = ''): string
    {
        $raw = (string) config('app.frontend_url', 'http://localhost:3000');
        $base = trim(explode(',', $raw)[0]);

        return rtrim($base, '/').($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    /**
     * Creates a Stripe Checkout session using treatment names and local GBP amounts.
     *
     * @param  array<int, array{name: string, amountPence: int, quantity?: int}>  $lineItems
     */
    public function createCheckoutSession(
        array $lineItems,
        string $purpose,
        int $customerId,
        ?string $customerEmail = null,
    ): Session {
        $this->bootstrap();

        $items = [];
        foreach ($lineItems as $line) {
            $amount = max(0, (int) ($line['amountPence'] ?? 0));
            if ($amount === 0) {
                continue;
            }

            $items[] = [
                'price_data' => [
                    'currency' => 'gbp',
                    'product_data' => [
                        'name' => (string) ($line['name'] ?? 'Treatment'),
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            ];
        }

        if ($items === []) {
            throw new \InvalidArgumentException('Checkout requires at least one priced line item.');
        }

        $params = [
            'mode' => 'payment',
            'line_items' => $items,
            'success_url' => $this->frontendUrl('payment/success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->frontendUrl('payment/cancel'),
            'metadata' => [
                'purpose' => $purpose,
                'customer_id' => (string) $customerId,
            ],
        ];

        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }

        return Session::create($params);
    }

    public function retrieve(string $sessionId): Session
    {
        $this->bootstrap();

        return Session::retrieve($sessionId);
    }

    public function isPaid(Session $session): bool
    {
        return $session->payment_status === 'paid';
    }

    private function bootstrap(): void
    {
        if (! $this->configured()) {
            throw new \RuntimeException('Stripe is not configured. Add your secret key in Admin → Web settings.');
        }

        $this->stripeConfig->apply();
        Stripe::setApiKey($this->stripeConfig->secretKey());
    }
}

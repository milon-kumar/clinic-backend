<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentSession;
use App\Models\Service;
use Stripe\Balance;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeAdminService
{
    public function __construct(
        private StripeCheckoutService $checkout,
        private StripeConfigService $stripeConfig,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $secret = $this->stripeConfig->secretKey();
        $publishable = $this->stripeConfig->publishableKey();
        $webhook = $this->stripeConfig->webhookSecret();

        return [
            'mode' => $this->stripeConfig->mode(),
            'enabled' => $this->stripeConfig->enabled(),
            'configSource' => $this->stripeConfig->configuredInSettings() ? 'Web settings' : 'Not configured',
            'secretConfigured' => filled($secret),
            'publishableKeyConfigured' => filled($publishable),
            'publishableKeyPreview' => $this->stripeConfig->maskKey($publishable),
            'webhookConfigured' => filled($webhook),
            'webhookUrl' => $this->stripeConfig->webhookUrl(),
            'successUrl' => $this->checkout->frontendUrl('payment/success'),
            'cancelUrl' => $this->checkout->frontendUrl('payment/cancel'),
            'stats' => [
                'totalSessions' => PaymentSession::query()->count(),
                'paidSessions' => PaymentSession::query()->where('status', 'paid')->count(),
                'pendingSessions' => PaymentSession::query()->where('status', 'pending')->count(),
                'totalPaidPence' => (int) PaymentSession::query()->where('status', 'paid')->sum('amount_pence'),
            ],
            'checkoutMode' => 'dynamic',
            'pricingSource' => 'Treatment £ amounts in Admin → Treatments',
            'dashboardUrl' => str_starts_with($secret, 'sk_live_')
                ? 'https://dashboard.stripe.com'
                : 'https://dashboard.stripe.com/test',
        ];
    }

    /**
     * @return array{ok: bool, message: string, currency?: string, availablePence?: int}
     */
    public function testConnection(): array
    {
        if (! $this->checkout->configured()) {
            return [
                'ok' => false,
                'message' => 'Stripe is not configured. Add your secret key in Admin → Web settings.',
            ];
        }

        try {
            $this->stripeConfig->apply();
            Stripe::setApiKey($this->stripeConfig->secretKey());
            $balance = Balance::retrieve();

            $available = collect($balance->available ?? [])->first();

            return [
                'ok' => true,
                'message' => 'Connected to Stripe successfully.',
                'currency' => strtoupper((string) ($available->currency ?? 'gbp')),
                'availablePence' => (int) ($available->amount ?? 0),
            ];
        } catch (ApiErrorException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  iterable<PaymentSession>  $sessions
     * @return array<string, string|null>
     */
    public function paymentTreatmentLabels(iterable $sessions): array
    {
        $sessions = collect($sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $serviceIds = [];
        $orderIds = [];
        $appointmentIds = [];
        $cartIds = [];

        foreach ($sessions as $session) {
            $payload = $session->payload ?? [];

            if (! empty($payload['serviceId'])) {
                $serviceIds[] = (int) $payload['serviceId'];
            }
            if (! empty($payload['orderId'])) {
                $orderIds[] = (int) $payload['orderId'];
            }
            if (! empty($payload['appointmentId'])) {
                $appointmentIds[] = (int) $payload['appointmentId'];
            }
            if ($session->purpose === 'buy' && ! empty($payload['cartId']) && empty($payload['orderId'])) {
                $cartIds[] = (int) $payload['cartId'];
            }
        }

        $services = Service::query()
            ->whereIn('id', array_unique($serviceIds))
            ->pluck('name', 'id');

        $orders = Order::query()
            ->with('lines.service')
            ->whereIn('id', array_unique($orderIds))
            ->get()
            ->keyBy('id');

        $appointments = Appointment::query()
            ->with('service')
            ->whereIn('id', array_unique($appointmentIds))
            ->get()
            ->keyBy('id');

        $carts = Cart::query()
            ->with('lines.service')
            ->whereIn('id', array_unique($cartIds))
            ->get()
            ->keyBy('id');

        $labels = [];

        foreach ($sessions as $session) {
            $payload = $session->payload ?? [];
            $names = [];

            if (! empty($payload['appointmentId'])) {
                $appointment = $appointments->get((int) $payload['appointmentId']);
                if ($appointment?->service?->name) {
                    $names[] = $appointment->service->name;
                }
            } elseif (! empty($payload['orderId'])) {
                $order = $orders->get((int) $payload['orderId']);
                foreach ($order?->lines ?? [] as $line) {
                    if ($line->service?->name) {
                        $names[] = $line->service->name;
                    }
                }
            } elseif (! empty($payload['serviceId'])) {
                $name = $services->get((int) $payload['serviceId']);
                if ($name) {
                    $names[] = $name;
                }
            } elseif (! empty($payload['cartId'])) {
                $cart = $carts->get((int) $payload['cartId']);
                foreach ($cart?->lines ?? [] as $line) {
                    if ($line->service?->name) {
                        $names[] = $line->service->name;
                    }
                }
            }

            $unique = array_values(array_unique(array_filter($names)));
            $labels[$session->id] = $unique !== [] ? implode(', ', $unique) : null;
        }

        return $labels;
    }

}

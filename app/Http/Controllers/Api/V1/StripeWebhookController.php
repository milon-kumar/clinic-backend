<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentSession;
use App\Models\User;
use App\Services\PaymentFulfillmentService;
use App\Services\StripeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(
        private PaymentFulfillmentService $fulfillment,
        private StripeConfigService $stripeConfig,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = $this->stripeConfig->webhookSecret();
        if (! filled($secret)) {
            return response()->json(['message' => 'Stripe webhook is not configured.'], 503);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(object $stripeSession): void
    {
        if (($stripeSession->payment_status ?? null) !== 'paid') {
            return;
        }

        $session = PaymentSession::find($stripeSession->id ?? null);
        if (! $session) {
            return;
        }

        $user = User::find($session->customer_id);
        if (! $user) {
            return;
        }

        $result = $this->fulfillment->fulfillIfPending($session, $user);
        if ($result['fulfilled']) {
            $this->fulfillment->notify($result['session']);
        }
    }
}

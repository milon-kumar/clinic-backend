<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSession;
use App\Services\StripeAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function __construct(
        private StripeAdminService $stripeAdmin,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        return response()->json(['data' => $this->stripeAdmin->overview()]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        return response()->json(['data' => $this->stripeAdmin->testConnection()]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);

        $filters = $request->validate([
            'status' => ['nullable', 'in:all,pending,paid,expired'],
            'purpose' => ['nullable', 'in:all,buy,book'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = PaymentSession::query()
            ->with('customer:id,name,email')
            ->orderByDesc('created_at');

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $purpose = $filters['purpose'] ?? 'all';
        if ($purpose !== 'all') {
            $query->where('purpose', $purpose);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('id', 'like', $term)
                    ->orWhereHas('customer', fn ($customer) => $customer
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term));
            });
        }

        $paginator = $query->paginate(25);
        $items = $paginator->items();
        $treatmentLabels = $this->stripeAdmin->paymentTreatmentLabels($items);

        return response()->json([
            'data' => collect($items)->map(
                fn (PaymentSession $session) => $this->paymentToApi($session, treatmentName: $treatmentLabels[$session->id] ?? null)
            )->values(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function payment(Request $request, string $sessionId): JsonResponse
    {
        $this->assertOrgAdmin($request);

        $session = PaymentSession::query()
            ->with('customer:id,name,email,phone')
            ->findOrFail($sessionId);

        $treatmentLabels = $this->stripeAdmin->paymentTreatmentLabels([$session]);

        return response()->json([
            'data' => $this->paymentToApi(
                $session,
                detailed: true,
                treatmentName: $treatmentLabels[$session->id] ?? null,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentToApi(PaymentSession $session, bool $detailed = false, ?string $treatmentName = null): array
    {
        $payload = $session->payload ?? [];

        $row = [
            'sessionId' => $session->id,
            'status' => $session->status,
            'purpose' => $session->purpose,
            'treatmentName' => $treatmentName,
            'amountPence' => $session->amount_pence,
            'amount' => $session->amount_pence / 100,
            'customerId' => $session->customer_id,
            'customerName' => $session->customer?->name,
            'customerEmail' => $session->customer?->email,
            'orderId' => $payload['orderId'] ?? null,
            'appointmentId' => $payload['appointmentId'] ?? null,
            'createdAt' => $session->created_at?->toIso8601String(),
            'expiresAt' => $session->expires_at?->toIso8601String(),
        ];

        if ($detailed) {
            $row['payload'] = $payload;
            $row['customerPhone'] = $session->customer?->phone;
        }

        return $row;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only a superadmin can manage Stripe settings.');
        }
    }
}

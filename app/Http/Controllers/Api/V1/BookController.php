<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\PrepaidPackage;
use App\Models\Service;
use App\Models\SlotHold;
use App\Services\AvailabilityService;
use App\Services\PackageService;
use App\Services\SlotHoldService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private SlotHoldService $slotHoldService,
        private PackageService $packageService,
    ) {}

    public function priorTreatment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hadPriorTreatment' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $this->bookCart($request);
        $meta = $cart->metadata ?? [];
        $meta['hadPriorTreatment'] = $data['hadPriorTreatment'];
        $meta['priorTreatmentNotes'] = $data['notes'] ?? null;
        $cart->update(['metadata' => $meta]);

        return response()->json(['data' => ['ok' => true, 'metadata' => $cart->metadata]]);
    }

    public function addons(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serviceIds' => ['required', 'array'],
            'serviceIds.*' => ['integer', 'exists:services,id'],
        ]);

        $cart = $this->bookCart($request);

        foreach ($data['serviceIds'] as $serviceId) {
            $exists = $cart->lines()->where('service_id', $serviceId)->exists();
            if ($exists) {
                continue;
            }

            $service = Service::findOrFail($serviceId);
            CartLine::create([
                'cart_id' => $cart->id,
                'service_id' => $service->id,
                'quantity' => 1,
                'unit_price_pence' => $service->base_price_pence,
            ]);
        }

        return response()->json([
            'data' => [
                'lines' => $cart->fresh('lines.service')->lines->map(fn ($line) => [
                    'lineId' => $line->id,
                    'serviceId' => $line->service_id,
                    'name' => $line->service->name,
                    'quantity' => $line->quantity,
                ]),
            ],
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'serviceId' => ['nullable', 'integer', 'exists:services,id'],
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date'],
            'timeOfDay' => ['nullable', 'string'],
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'] ?? $data['from'])->endOfDay();
        $serviceIds = array_filter([$data['serviceId'] ?? null]);

        $slots = $this->availabilityService->getSlots(
            (int) $data['clinicId'],
            $serviceIds,
            $from,
            $to,
            $data['timeOfDay'] ?? null
        );

        return response()->json(['data' => $slots->values()]);
    }

    public function createHold(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'startsAt' => ['required', 'date'],
        ]);

        try {
            $hold = $this->slotHoldService->createHold(
                (int) $data['clinicId'],
                (int) $data['serviceId'],
                $data['startsAt'],
                $request->user()->id
            );
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'startsAt' => [$e->getMessage()],
            ]);
        }

        return response()->json(['data' => $hold], 201);
    }

    public function releaseHold(string $holdId): JsonResponse
    {
        $released = $this->slotHoldService->releaseHold($holdId);

        return response()->json(['data' => ['released' => $released]]);
    }

    public function confirmAppointment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'holdId' => ['required', 'string'],
            'fullName' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paymentMethod' => ['nullable', 'in:cash,online,package,pay_at_clinic,card'],
            'packageId' => ['nullable'],
        ]);

        $user = $request->user();
        $hold = SlotHold::findOrFail($data['holdId']);
        $service = Service::find($hold->service_id);
        $amountPence = $service?->appointmentAmountPence() ?? 0;
        $paymentMethod = $data['paymentMethod'] ?? 'cash';
        if ($paymentMethod === 'pay_at_clinic') {
            $paymentMethod = 'cash';
        }

        $appointmentData = [
            'customer_id' => $user->id,
            'full_name' => $data['fullName'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? $user->email,
            'notes' => $data['notes'] ?? null,
            'amount_pence' => $amountPence,
            'payment_method' => $amountPence === 0 ? 'free' : $paymentMethod,
            'payment_status' => $amountPence === 0 ? 'free' : ($paymentMethod === 'cash' ? 'pay_at_clinic' : 'paid'),
            'status' => 'confirmed',
        ];

        if (! empty($data['packageId'])) {
            $package = PrepaidPackage::query()->lockForUpdate()->findOrFail($data['packageId']);
            if ($package->customer_id !== $user->id) {
                abort(403);
            }
            if ((int) $package->clinic_id !== (int) $hold->clinic_id) {
                throw ValidationException::withMessages([
                    'packageId' => ['Package cannot be redeemed at a different clinic.'],
                ]);
            }
            if ((int) $package->service_id !== (int) $hold->service_id) {
                throw ValidationException::withMessages([
                    'packageId' => ['Package does not match this treatment.'],
                ]);
            }
            $this->packageService->redeem($package->id, 0);
            $appointmentData['package_id'] = $package->id;
            $appointmentData['payment_method'] = 'package';
            $appointmentData['payment_status'] = 'redeemed';
        }

        try {
            $appointment = $this->slotHoldService->confirmHold($data['holdId'], $appointmentData);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'holdId' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'data' => $appointment->toApi(),
        ], 201);
    }

    private function bookCart(Request $request): Cart
    {
        return Cart::firstOrCreate(
            [
                'customer_id' => $request->user()->id,
                'cart_type' => 'book',
            ],
            [
                'clinic_id' => $request->user()->selected_clinic_id,
            ]
        );
    }
}

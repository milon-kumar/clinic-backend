<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use App\Services\SessionWorkflowService;
use App\Support\BranchScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private SessionWorkflowService $sessionWorkflow,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query()->with(['clinic', 'service', 'customer', 'nextAppointment', 'prepaidPackage']);
        BranchScope::apply($query, $request->user());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($clinicId = $request->query('clinicId')) {
            $query->where('clinic_id', $clinicId);
        }

        if ($search = $request->query('search', $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $appointments = $query->orderByDesc('appointment_date')->orderBy('appointment_time')->get()->map->toApi()->values();

        return response()->json(['data' => $appointments]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::query()->with(['clinic', 'service', 'customer', 'nextAppointment', 'prepaidPackage'])->findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);

        return response()->json(['data' => $appointment->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customerId' => ['nullable', 'integer', 'exists:users,id'],
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'treatmentId' => ['nullable', 'integer', 'exists:services,id'],
            'appointmentDate' => ['required', 'date'],
            'appointmentTime' => ['required', 'string'],
            'fullName' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'paymentStatus' => ['nullable', 'string'],
            'paymentMethod' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'amountPence' => ['nullable', 'integer', 'min:0'],
        ]);

        BranchScope::assert($request->user(), (int) $data['clinicId']);

        $service = Service::find($data['serviceId'] ?? $data['treatmentId'] ?? null);
        $amountPence = array_key_exists('amountPence', $data)
            ? (int) $data['amountPence']
            : (array_key_exists('amount', $data)
                ? (int) round($data['amount'] * 100)
                : ($service?->appointmentAmountPence() ?? 0));
        $paymentMethod = $amountPence === 0 ? 'free' : ($data['paymentMethod'] ?? 'cash');
        $paymentStatus = $data['paymentStatus'] ?? ($amountPence === 0 ? 'free' : null);

        $appointment = Appointment::create([
            'customer_id' => $data['customerId'] ?? $request->user()->id,
            'clinic_id' => $data['clinicId'],
            'service_id' => $data['serviceId'] ?? $data['treatmentId'],
            'full_name' => $data['fullName'] ?? $request->user()->name,
            'phone' => $data['phone'] ?? $request->user()->phone,
            'email' => $data['email'] ?? $request->user()->email,
            'notes' => $data['notes'] ?? null,
            'appointment_date' => Carbon::parse($data['appointmentDate'])->toDateString(),
            'appointment_time' => $data['appointmentTime'],
            'status' => $data['status'] ?? 'pending',
            'amount_pence' => $amountPence,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'qr_token' => (string) Str::uuid(),
        ]);

        return response()->json(['data' => $appointment->load(['clinic', 'service'])->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);

        $data = $request->validate([
            'appointmentDate' => ['nullable', 'date'],
            'appointmentTime' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'paymentStatus' => ['nullable', 'string'],
            'paymentMethod' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'amountPence' => ['nullable', 'integer', 'min:0'],
        ]);

        $updates = array_filter([
            'appointment_date' => isset($data['appointmentDate']) ? Carbon::parse($data['appointmentDate'])->toDateString() : null,
            'appointment_time' => $data['appointmentTime'] ?? null,
            'status' => $data['status'] ?? null,
            'notes' => $data['notes'] ?? null,
            'payment_status' => $data['paymentStatus'] ?? null,
            'payment_method' => $data['paymentMethod'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('amountPence', $data)) {
            $updates['amount_pence'] = (int) $data['amountPence'];
        } elseif (array_key_exists('amount', $data)) {
            $updates['amount_pence'] = (int) round($data['amount'] * 100);
        }

        if (array_key_exists('amount_pence', $updates) && $updates['amount_pence'] === 0) {
            $updates['payment_method'] = $updates['payment_method'] ?? 'free';
            $updates['payment_status'] = $updates['payment_status'] ?? 'free';
        }

        $appointment->update($updates);

        return response()->json(['data' => $appointment->fresh(['clinic', 'service'])->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted']);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);
        $appointment->update(['status' => 'confirmed']);

        return response()->json(['data' => $appointment->fresh(['clinic', 'service'])->toApi()]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);
        $appointment->update(['status' => 'cancelled']);
        $fresh = $appointment->fresh(['clinic', 'service']);
        try {
            $this->notifications->appointmentCancelled($fresh);
        } catch (\Throwable $e) {
            Log::warning('In-app notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['data' => $fresh->toApi()]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::query()->with(['clinic', 'service', 'prepaidPackage'])->findOrFail($id);
        BranchScope::assert($request->user(), (int) $appointment->clinic_id);

        $data = $request->validate([
            'action' => ['nullable', 'in:complete,defer'],
            'nextAppointmentDate' => ['nullable', 'date'],
            'nextAppointmentTime' => ['nullable', 'string', 'max:40'],
        ]);

        $nextDate = $data['nextAppointmentDate'] ?? null;
        $nextTime = $data['nextAppointmentTime'] ?? null;

        if (($data['action'] ?? 'complete') === 'defer') {
            $updated = $this->sessionWorkflow->defer($appointment, $nextDate, $nextTime);

            return response()->json(['data' => $updated->toApi()]);
        }

        $result = $this->sessionWorkflow->complete($appointment, $nextDate, $nextTime);

        return response()->json([
            'data' => $result['appointment']->toApi(),
            'next' => $result['next']?->toApi(),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Appointment::query();
        BranchScope::apply($query, $request->user());

        return response()->json([
            'data' => [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->where('status', 'pending')->count(),
                'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'from' => ['required', 'date'],
            'to' => ['nullable', 'date'],
            'serviceId' => ['nullable', 'integer'],
        ]);

        BranchScope::assert($request->user(), (int) $data['clinicId']);

        $slots = $this->availabilityService->getSlots(
            (int) $data['clinicId'],
            array_filter([$data['serviceId'] ?? null]),
            Carbon::parse($data['from'])->startOfDay(),
            Carbon::parse($data['to'] ?? $data['from'])->endOfDay()
        );

        return response()->json(['data' => $slots->values()]);
    }
}

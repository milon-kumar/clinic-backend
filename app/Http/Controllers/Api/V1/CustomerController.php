<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\PackageService;
use App\Services\TreatmentJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private PackageService $packageService,
        private TreatmentJourneyService $journeys,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->toApi(),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'dateOfBirth' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $user->update([
            'first_name' => $data['firstName'] ?? $user->first_name,
            'last_name' => $data['lastName'] ?? $user->last_name,
            'name' => trim(($data['firstName'] ?? $user->first_name).' '.($data['lastName'] ?? $user->last_name)),
            'username' => $data['username'] ?? $user->username,
            'phone' => $data['phone'] ?? $user->phone,
            'address' => $data['address'] ?? $user->address,
            'date_of_birth' => $data['dateOfBirth'] ?? $user->date_of_birth,
        ]);

        return response()->json(['data' => $user->fresh()->toApi()]);
    }

    public function appointments(Request $request): JsonResponse
    {
        $appointments = Appointment::query()
            ->where('customer_id', $request->user()->id)
            ->with(['clinic', 'service', 'nextAppointment', 'prepaidPackage', 'previousAppointment', 'review'])
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->get()
            ->map(fn (Appointment $appointment) => $this->journeys->decorate($appointment))
            ->values();

        return response()->json(['data' => $appointments]);
    }

    public function appointment(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::query()
            ->where('customer_id', $request->user()->id)
            ->with(['clinic', 'service', 'nextAppointment', 'prepaidPackage', 'previousAppointment', 'review'])
            ->findOrFail($id);

        return response()->json(['data' => $this->journeys->decorate($appointment)]);
    }

    public function packages(Request $request): JsonResponse
    {
        $clinicId = $request->query('clinicId');

        if ($clinicId && $request->boolean('redeemable')) {
            $packages = $this->packageService
                ->listRedeemable($request->user()->id, (int) $clinicId)
                ->map->toApi()
                ->values();
        } else {
            $packages = $this->journeys->packagesForCustomer(
                $request->user(),
                $clinicId ? (int) $clinicId : null,
            );
        }

        return response()->json(['data' => $packages]);
    }
}

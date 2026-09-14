<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrepaidPackage;
use App\Services\LocalTreatmentService;
use App\Services\SessionWorkflowService;
use App\Services\TreatmentJourneyService;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentJourneyController extends Controller
{
    public function __construct(
        private TreatmentJourneyService $journeys,
        private LocalTreatmentService $localTreatments,
        private SessionWorkflowService $sessions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
        ]);

        return response()->json([
            'data' => $this->journeys->listForAdmin(
                $request->user(),
                $data['search'] ?? null,
                isset($data['clinicId']) ? (int) $data['clinicId'] : null,
            ),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->journeys->findForAdmin($request->user(), $id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'customerId' => ['nullable', 'integer', 'exists:users,id'],
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'sessions' => ['nullable', 'integer', 'min:1', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'pricePence' => ['nullable', 'integer', 'min:0'],
            'bookingDate' => ['nullable', 'date'],
            'bookingTime' => ['nullable', 'string', 'max:40'],
            'paymentMethod' => ['nullable', 'string', 'max:40'],
        ]);

        BranchScope::assert($request->user(), (int) $data['clinicId']);

        return response()->json([
            'data' => $this->localTreatments->create($request->user(), $data),
        ], 201);
    }

    public function startSession(Request $request, string $id): JsonResponse
    {
        if (! ctype_digit($id)) {
            abort(404, 'Treatment order not found');
        }

        $package = PrepaidPackage::query()->with(['customer', 'clinic', 'service'])->findOrFail((int) $id);
        BranchScope::assert($request->user(), (int) $package->clinic_id);

        $data = $request->validate([
            'appointmentDate' => ['nullable', 'date'],
            'appointmentTime' => ['nullable', 'string', 'max:40'],
            'sessionNotes' => ['nullable', 'string', 'max:4000'],
            'complete' => ['nullable', 'boolean'],
            'nextAppointmentDate' => ['nullable', 'date'],
            'nextAppointmentTime' => ['nullable', 'string', 'max:40'],
        ]);

        $this->sessions->startFromPackage(
            $package,
            $data['appointmentDate'] ?? null,
            $data['appointmentTime'] ?? null,
            $data['sessionNotes'] ?? null,
            (bool) ($data['complete'] ?? false),
            $data['nextAppointmentDate'] ?? null,
            $data['nextAppointmentTime'] ?? null,
        );

        return response()->json([
            'message' => ! empty($data['complete'])
                ? 'Session marked complete.'
                : 'Session started.',
            'data' => $this->journeys->findForAdmin($request->user(), $id),
        ]);
    }
}

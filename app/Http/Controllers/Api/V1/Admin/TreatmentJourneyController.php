<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\TreatmentJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreatmentJourneyController extends Controller
{
    public function __construct(private TreatmentJourneyService $journeys) {}

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
}

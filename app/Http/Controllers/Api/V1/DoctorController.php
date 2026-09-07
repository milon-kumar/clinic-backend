<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::query()
            ->where('is_active', true)
            ->with(['clinic.schedules', 'clinics.schedules'])
            ->orderBy('name');

        if ($clinicId = $request->query('clinicId')) {
            $query->whereHas('clinics', fn ($q) => $q->where('clinics.id', (int) $clinicId));
        }

        return response()->json([
            'data' => $query->get()->map->toApi()->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $doctor = Doctor::query()
            ->where('is_active', true)
            ->with(['clinic.schedules', 'clinics.schedules'])
            ->findOrFail($id);

        return response()->json(['data' => $doctor->toApi()]);
    }
}

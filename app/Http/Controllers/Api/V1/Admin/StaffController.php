<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicStaff;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $id);

        $staff = ClinicStaff::query()
            ->where('clinic_id', $id)
            ->with(['user.clinic', 'clinic'])
            ->orderBy('id')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $staff]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $id);

        $data = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'jobTitle' => ['nullable', 'string', 'max:120'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $user = User::findOrFail($data['userId']);

        if ($user->role === Roles::PATIENT) {
            abort(422, 'Give this person a staff role before assigning them to a branch.');
        }

        if (Roles::isSuperAdmin($user->role) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a superadmin can assign organisation admins.');
        }

        $row = ClinicStaff::updateOrCreate(
            [
                'clinic_id' => $id,
                'user_id' => $user->id,
            ],
            [
                'job_title' => $data['jobTitle'] ?? null,
                'is_active' => $data['isActive'] ?? true,
            ]
        );

        if (! $user->clinic_id) {
            $user->update(['clinic_id' => $id]);
        }

        return response()->json(['data' => $row->load(['user', 'clinic'])->toApi()], 201);
    }

    public function destroy(Request $request, int $id, int $userId): JsonResponse
    {
        Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $id);

        ClinicStaff::query()
            ->where('clinic_id', $id)
            ->where('user_id', $userId)
            ->delete();

        $user = User::find($userId);
        if ($user && (int) $user->clinic_id === $id) {
            $next = ClinicStaff::query()->where('user_id', $userId)->first();
            $user->update(['clinic_id' => $next?->clinic_id]);
        }

        return response()->json(['message' => 'Staff removed from branch']);
    }

    public function schedules(Request $request): JsonResponse
    {
        $query = StaffSchedule::query()->with(['user', 'clinic'])->orderBy('day_of_week');

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->where('clinic_id', $clinicId);
        } else {
            BranchScope::apply($query, $request->user());
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'userId' => ['required', 'integer', 'exists:users,id'],
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'string'],
            'endTime' => ['required', 'string'],
        ]);

        BranchScope::assert($request->user(), (int) $data['clinicId']);

        $assigned = ClinicStaff::query()
            ->where('clinic_id', $data['clinicId'])
            ->where('user_id', $data['userId'])
            ->exists();

        if (! $assigned) {
            abort(422, 'Staff must be assigned to this branch first.');
        }

        $row = StaffSchedule::create([
            'clinic_id' => $data['clinicId'],
            'user_id' => $data['userId'],
            'day_of_week' => $data['dayOfWeek'],
            'start_time' => $data['startTime'],
            'end_time' => $data['endTime'],
        ]);

        return response()->json(['data' => $row->load(['user', 'clinic'])->toApi()], 201);
    }

    public function destroySchedule(Request $request, int $id): JsonResponse
    {
        $row = StaffSchedule::findOrFail($id);
        BranchScope::assert($request->user(), (int) $row->clinic_id);
        $row->delete();

        return response()->json(['message' => 'Shift deleted']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClinicStaff;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\Roles;
use App\Support\UserMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('clinic')->orderByDesc('id');

        if (! $request->user()->isSuperAdmin()) {
            $ids = $request->user()->accessibleClinicIds();
            $query->where(function ($q) use ($ids) {
                $q->whereIn('clinic_id', $ids ?: [0])
                    ->orWhere('role', Roles::PATIENT)
                    ->orWhereHas('staffAssignments', fn ($s) => $s->whereIn('clinic_id', $ids ?: [0]));
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($search = $request->query('search', $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map->toApi()->values();

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::query()->with('clinic')->findOrFail($id);
        $this->assertCanManage($request, $user);

        return response()->json(['data' => $user->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(Roles::assignable())],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
            'jobTitle' => ['nullable', 'string', 'max:120'],
        ]);

        $this->assertCanAssignRole($request, $data['role']);

        if (! empty($data['clinicId'])) {
            BranchScope::assert($request->user(), (int) $data['clinicId']);
        }

        $payload = $data['role'] === Roles::PATIENT
            ? array_merge(UserMapper::fromRegister($data), ['is_verified' => true, 'email_verified_at' => now(), 'role' => Roles::PATIENT])
            : UserMapper::staffPayload($data);

        $user = User::create($payload);

        if ($user->role !== Roles::PATIENT && ! empty($data['clinicId'])) {
            ClinicStaff::updateOrCreate(
                ['clinic_id' => $data['clinicId'], 'user_id' => $user->id],
                ['job_title' => $data['jobTitle'] ?? null, 'is_active' => true]
            );
        }

        return response()->json(['data' => $user->load('clinic')->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->assertCanManage($request, $user);

        $data = $request->validate([
            'firstName' => ['nullable', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'max:80', 'unique:users,username,'.$id],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(Roles::assignable())],
            'isVerified' => ['nullable', 'boolean'],
            'selectedClinicId' => ['nullable', 'integer', 'exists:clinics,id'],
            'clinicId' => ['nullable', 'integer', 'exists:clinics,id'],
            'jobTitle' => ['nullable', 'string', 'max:120'],
        ]);

        if (isset($data['role'])) {
            $this->assertCanAssignRole($request, $data['role']);
        }

        $user->update(array_filter([
            'first_name' => $data['firstName'] ?? null,
            'last_name' => $data['lastName'] ?? null,
            'name' => isset($data['firstName']) || isset($data['lastName'])
                ? trim(($data['firstName'] ?? $user->first_name).' '.($data['lastName'] ?? $user->last_name))
                : null,
            'username' => $data['username'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'role' => $data['role'] ?? null,
            'is_verified' => $data['isVerified'] ?? null,
            'selected_clinic_id' => $data['selectedClinicId'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('clinicId', $data)) {
            if ($data['clinicId']) {
                BranchScope::assert($request->user(), (int) $data['clinicId']);
            }
            $user->update(['clinic_id' => $data['clinicId'] ?: null]);
        }

        if (! empty($data['clinicId']) && $user->role !== Roles::PATIENT) {
            ClinicStaff::updateOrCreate(
                ['clinic_id' => $data['clinicId'], 'user_id' => $user->id],
                [
                    'job_title' => $data['jobTitle'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        return response()->json(['data' => $user->fresh('clinic')->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()?->id) {
            abort(422, 'You cannot delete your own account.');
        }

        $this->assertCanManage($request, $user);

        if (Roles::isSuperAdmin($user->role) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a superadmin can delete organisation admins.');
        }

        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    private function assertCanAssignRole(Request $request, string $role): void
    {
        if (Roles::isSuperAdmin($role) && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a superadmin can assign organisation admin roles.');
        }
    }

    private function assertCanManage(Request $request, User $user): void
    {
        if ($request->user()->isSuperAdmin() || $user->role === Roles::PATIENT) {
            return;
        }

        if ($user->clinic_id && $request->user()->canAccessClinic((int) $user->clinic_id)) {
            return;
        }

        $overlap = $user->staffAssignments()
            ->whereIn('clinic_id', $request->user()->accessibleClinicIds() ?: [0])
            ->exists();

        if (! $overlap) {
            abort(403, 'This person is outside your branch.');
        }
    }
}

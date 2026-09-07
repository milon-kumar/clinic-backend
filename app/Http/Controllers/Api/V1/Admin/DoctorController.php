<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Doctor::query()->with(['clinic', 'clinics'])->orderBy('name');

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->whereHas('clinics', fn ($q) => $q->where('clinics.id', (int) $clinicId));
        } elseif (! $request->user()->isSuperAdmin()) {
            $ids = $request->user()->accessibleClinicIds();
            $query->whereHas('clinics', fn ($q) => $q->whereIn('clinics.id', $ids ?: [0]));
        }

        return response()->json([
            'data' => $query->get()->map->toApi()->values(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $doctor = Doctor::query()->with(['clinic', 'clinics'])->findOrFail($id);
        $this->assertCanView($request, $doctor);

        return response()->json(['data' => $doctor->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $data = $this->validated($request);
        $clinicIds = $this->clinicIdsFrom($data);

        if ($clinicIds === []) {
            abort(422, 'Assign the doctor to at least one clinic.');
        }

        $doctor = Doctor::create(array_merge($this->doctorAttrs($data), [
            'is_active' => $data['isActive'] ?? true,
            'clinic_id' => $clinicIds[0] ?? null,
        ]));
        $doctor->syncClinics($clinicIds);

        return response()->json([
            'data' => $doctor->fresh(['clinic', 'clinics'])->toApi(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $doctor = Doctor::findOrFail($id);
        $data = $this->validated($request, updating: true);

        $attrs = $this->doctorAttrs($data);
        if (array_key_exists('isActive', $data)) {
            $attrs['is_active'] = $data['isActive'];
        }
        $doctor->update($attrs);

        if (array_key_exists('clinicIds', $data) || ! empty($data['offerAtAllClinics'])) {
            $doctor->syncClinics($this->clinicIdsFrom($data));
        }

        return response()->json([
            'data' => $doctor->fresh(['clinic', 'clinics'])->toApi(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        Doctor::findOrFail($id)->delete();

        return response()->json(['message' => 'Doctor deleted']);
    }

    public function upload(Request $request): JsonResponse
    {
        $kind = $request->input('kind', 'image');

        if ($kind === 'cv') {
            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            ]);
            $path = $request->file('file')->store('doctors/cv', 'public');
        } else {
            $request->validate([
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]);
            $path = $request->file('file')->store('doctors', 'public');
        }

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $id);

        $data = $request->validate([
            'doctorId' => ['required', 'integer', 'exists:doctors,id'],
        ]);

        $doctor = Doctor::findOrFail($data['doctorId']);
        $doctor->assignClinic($id);

        return response()->json([
            'data' => $doctor->fresh(['clinic', 'clinics'])->toApi(),
        ]);
    }

    public function unassign(Request $request, int $id, int $doctorId): JsonResponse
    {
        Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $id);

        $doctor = Doctor::findOrFail($doctorId);
        $doctor->unassignClinic($id);

        return response()->json([
            'data' => $doctor->fresh(['clinic', 'clinics'])->toApi(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $name = $updating ? ['sometimes', 'required', 'string', 'max:160'] : ['required', 'string', 'max:160'];

        return $request->validate([
            'name' => $name,
            'kicker' => ['nullable', 'string', 'max:160'],
            'nameAccent' => ['nullable', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'professionalTitle' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'cv' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'credentials.*.label' => ['nullable', 'string', 'max:80'],
            'credentials.*.detail' => ['nullable', 'string', 'max:160'],
            'expertiseKicker' => ['nullable', 'string', 'max:80'],
            'expertiseHeading' => ['nullable', 'string', 'max:160'],
            'expertiseTags' => ['nullable', 'string', 'max:255'],
            'expertise' => ['nullable', 'array'],
            'expertise.*.icon' => ['nullable', 'string', 'max:80'],
            'expertise.*.title' => ['nullable', 'string', 'max:120'],
            'expertise.*.description' => ['nullable', 'string'],
            'education' => ['nullable', 'array'],
            'education.*.years' => ['nullable', 'string', 'max:80'],
            'education.*.title' => ['nullable', 'string', 'max:160'],
            'education.*.school' => ['nullable', 'string', 'max:160'],
            'education.*.place' => ['nullable', 'string', 'max:160'],
            'education.*.note' => ['nullable', 'string', 'max:160'],
            'promises' => ['nullable', 'array'],
            'promises.*.title' => ['nullable', 'string', 'max:120'],
            'promises.*.copy' => ['nullable', 'string'],
            'ctaTitle' => ['nullable', 'string', 'max:160'],
            'ctaCopy' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
            'offerAtAllClinics' => ['nullable', 'boolean'],
            'clinicIds' => ['nullable', 'array'],
            'clinicIds.*' => ['integer', 'exists:clinics,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function doctorAttrs(array $data): array
    {
        $map = [
            'name' => 'name',
            'kicker' => 'kicker',
            'nameAccent' => 'name_accent',
            'tagline' => 'tagline',
            'specialty' => 'specialty',
            'professionalTitle' => 'professional_title',
            'bio' => 'bio',
            'image' => 'image',
            'cv' => 'cv',
            'expertiseKicker' => 'expertise_kicker',
            'expertiseHeading' => 'expertise_heading',
            'expertiseTags' => 'expertise_tags',
            'ctaTitle' => 'cta_title',
            'ctaCopy' => 'cta_copy',
        ];

        $attrs = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $value = $data[$input];
                $attrs[$column] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('credentials', $data)) {
            $attrs['credentials'] = $this->cleanList($data['credentials'] ?? [], ['label', 'detail']);
        }
        if (array_key_exists('expertise', $data)) {
            $attrs['expertise'] = $this->cleanList($data['expertise'] ?? [], ['icon', 'title', 'description'], 'title');
        }
        if (array_key_exists('education', $data)) {
            $attrs['education'] = $this->cleanList($data['education'] ?? [], ['years', 'title', 'school', 'place', 'note'], 'title');
        }
        if (array_key_exists('promises', $data)) {
            $attrs['promises'] = $this->cleanList($data['promises'] ?? [], ['title', 'copy'], 'title');
        }

        return $attrs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function cleanList(array $rows, array $keys, ?string $required = null): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $item = [];
            foreach ($keys as $key) {
                $value = is_array($row) ? ($row[$key] ?? null) : null;
                $item[$key] = is_string($value) ? trim($value) : $value;
                if ($item[$key] === '') {
                    $item[$key] = null;
                }
            }
            if ($required && empty($item[$required])) {
                continue;
            }
            if (! $required && empty(array_filter($item))) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function clinicIdsFrom(array $data): array
    {
        if (! empty($data['offerAtAllClinics'])) {
            return Clinic::query()->orderBy('name')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return array_values(array_unique(array_map('intval', $data['clinicIds'] ?? [])));
    }

    private function assertCanView(Request $request, Doctor $doctor): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        $accessible = $request->user()->accessibleClinicIds();
        $doctorClinicIds = $doctor->clinics->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! array_intersect($accessible, $doctorClinicIds)) {
            abort(403, 'This doctor is outside your branch.');
        }
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only a superadmin can add or edit doctors.');
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicClosure;
use App\Models\ClinicSchedule;
use App\Models\ClinicService;
use App\Models\Service;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClinicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Clinic::query()->with('schedules')->orderBy('name');
        BranchScope::apply($query, $request->user(), 'id');

        $clinics = $query->get()->map->toApi()->values();

        return response()->json(['data' => $clinics]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $clinic = $this->clinicFor($request, $id);

        return response()->json(['data' => $clinic->load('schedules')->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $data = $this->validatedClinic($request);
        $clinic = Clinic::create($this->clinicAttrs($data));

        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            ClinicSchedule::create([
                'clinic_id' => $clinic->id,
                'day_of_week' => $day,
                'open_time' => '09:00:00',
                'close_time' => '18:00:00',
                'slot_interval_minutes' => 60,
            ]);
        }

        return response()->json(['data' => $clinic->load('schedules')->toApi()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $clinic = $this->clinicFor($request, $id);
        $data = $this->validatedClinic($request, $clinic->id);
        $clinic->update($this->clinicAttrs($data, $clinic));

        return response()->json(['data' => $clinic->fresh('schedules')->toApi()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $this->clinicFor($request, $id)->delete();

        return response()->json(['message' => 'Clinic deleted']);
    }

    public function matrix(Request $request, int $id): JsonResponse
    {
        $clinic = $this->clinicFor($request, $id);
        $rows = ClinicService::query()
            ->where('clinic_id', $clinic->id)
            ->with('service')
            ->get()
            ->keyBy('service_id');

        $services = Service::query()->where('is_active', true)->orderBy('name')->get()->map(function (Service $service) use ($rows) {
            $row = $rows->get($service->id);

            return [
                'serviceId' => $service->id,
                'name' => $service->name,
                'sku' => $service->sku,
                'slug' => $service->slug,
                'offered' => (bool) $row,
                'buyEnabled' => (bool) ($row?->buy_enabled),
                'bookEnabled' => (bool) ($row?->book_enabled),
                'onlineBuyEnabled' => (bool) ($row?->online_buy_enabled),
                'pricePence' => $row?->price_pence,
                'basePricePence' => $service->base_price_pence,
            ];
        });

        return response()->json([
            'data' => [
                'clinic' => $clinic->toApi(),
                'services' => $services,
            ],
        ]);
    }

    public function updateMatrix(Request $request, int $id): JsonResponse
    {
        $clinic = $this->clinicFor($request, $id);
        $data = $request->validate([
            'services' => ['required', 'array'],
            'services.*.serviceId' => ['required', 'integer', 'exists:services,id'],
            'services.*.offered' => ['nullable', 'boolean'],
            'services.*.buyEnabled' => ['nullable', 'boolean'],
            'services.*.bookEnabled' => ['nullable', 'boolean'],
            'services.*.onlineBuyEnabled' => ['nullable', 'boolean'],
            'services.*.pricePence' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['services'] as $item) {
            $offered = $item['offered'] ?? true;
            if (! $offered) {
                ClinicService::query()
                    ->where('clinic_id', $clinic->id)
                    ->where('service_id', $item['serviceId'])
                    ->delete();
                continue;
            }

            ClinicService::updateOrCreate(
                [
                    'clinic_id' => $clinic->id,
                    'service_id' => $item['serviceId'],
                ],
                [
                    'buy_enabled' => $item['buyEnabled'] ?? false,
                    'book_enabled' => $item['bookEnabled'] ?? false,
                    'online_buy_enabled' => $item['onlineBuyEnabled'] ?? false,
                    'price_pence' => $item['pricePence'] ?? null,
                ]
            );
        }

        return $this->matrix($request, $clinic->id);
    }

    public function schedules(Request $request, int $id): JsonResponse
    {
        $clinic = $this->clinicFor($request, $id);
        $schedules = $clinic->schedules()->orderBy('day_of_week')->get()->map(fn (ClinicSchedule $s) => [
            'id' => $s->id,
            'dayOfWeek' => $s->day_of_week,
            'openTime' => $s->open_time,
            'closeTime' => $s->close_time,
            'slotIntervalMinutes' => $s->slot_interval_minutes,
        ]);

        return response()->json(['data' => $schedules]);
    }

    public function storeSchedule(Request $request, int $id): JsonResponse
    {
        $this->clinicFor($request, $id);
        $data = $request->validate([
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'openTime' => ['required', 'string'],
            'closeTime' => ['required', 'string'],
            'slotIntervalMinutes' => ['nullable', 'integer', 'min:15'],
        ]);

        $schedule = ClinicSchedule::create([
            'clinic_id' => $id,
            'day_of_week' => $data['dayOfWeek'],
            'open_time' => $data['openTime'],
            'close_time' => $data['closeTime'],
            'slot_interval_minutes' => $data['slotIntervalMinutes'] ?? 60,
        ]);

        return response()->json(['data' => [
            'id' => $schedule->id,
            'dayOfWeek' => $schedule->day_of_week,
            'openTime' => $schedule->open_time,
            'closeTime' => $schedule->close_time,
            'slotIntervalMinutes' => $schedule->slot_interval_minutes,
        ]], 201);
    }

    public function updateSchedule(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = ClinicSchedule::findOrFail($scheduleId);
        BranchScope::assert($request->user(), (int) $schedule->clinic_id);
        $data = $request->validate([
            'dayOfWeek' => ['nullable', 'integer', 'between:0,6'],
            'openTime' => ['nullable', 'string'],
            'closeTime' => ['nullable', 'string'],
            'slotIntervalMinutes' => ['nullable', 'integer', 'min:15'],
        ]);

        $schedule->update(array_filter([
            'day_of_week' => $data['dayOfWeek'] ?? null,
            'open_time' => $data['openTime'] ?? null,
            'close_time' => $data['closeTime'] ?? null,
            'slot_interval_minutes' => $data['slotIntervalMinutes'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => [
            'id' => $schedule->id,
            'dayOfWeek' => $schedule->day_of_week,
            'openTime' => $schedule->open_time,
            'closeTime' => $schedule->close_time,
            'slotIntervalMinutes' => $schedule->slot_interval_minutes,
        ]]);
    }

    public function destroySchedule(Request $request, int $scheduleId): JsonResponse
    {
        $schedule = ClinicSchedule::findOrFail($scheduleId);
        BranchScope::assert($request->user(), (int) $schedule->clinic_id);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted']);
    }

    public function closures(Request $request, int $id): JsonResponse
    {
        $this->clinicFor($request, $id);
        $closures = ClinicClosure::query()
            ->where('clinic_id', $id)
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (ClinicClosure $c) => [
                'id' => $c->id,
                'startsAt' => $c->starts_at?->toIso8601String(),
                'endsAt' => $c->ends_at?->toIso8601String(),
                'reason' => $c->reason,
            ]);

        return response()->json(['data' => $closures]);
    }

    public function storeClosure(Request $request, int $id): JsonResponse
    {
        $this->clinicFor($request, $id);
        $data = $request->validate([
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after_or_equal:startsAt'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $closure = ClinicClosure::create([
            'clinic_id' => $id,
            'starts_at' => $data['startsAt'],
            'ends_at' => $data['endsAt'],
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['data' => [
            'id' => $closure->id,
            'startsAt' => $closure->starts_at->toIso8601String(),
            'endsAt' => $closure->ends_at->toIso8601String(),
            'reason' => $closure->reason,
        ]], 201);
    }

    public function destroyClosure(Request $request, int $closureId): JsonResponse
    {
        $closure = ClinicClosure::findOrFail($closureId);
        BranchScope::assert($request->user(), (int) $closure->clinic_id);
        $closure->delete();

        return response()->json(['message' => 'Closure deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedClinic(Request $request, ?int $id = null): array
    {
        $slugRule = $id ? 'unique:clinics,slug,'.$id : 'unique:clinics,slug';
        $codeRule = $id ? 'unique:clinics,code,'.$id : 'unique:clinics,code';

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', $slugRule],
            'code' => ['nullable', 'string', 'max:80', $codeRule],
            'region' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'isActive' => ['nullable', 'boolean'],
            'address' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clinicAttrs(array $data, ?Clinic $clinic = null): array
    {
        $name = $data['name'];
        $slug = $data['slug'] ?? $clinic?->slug ?? Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'code' => $data['code'] ?? $clinic?->code ?? strtoupper(str_replace('-', '_', $slug)),
            'region' => $data['region'] ?? $clinic?->region,
            'phone' => $data['phone'] ?? $clinic?->phone,
            'timezone' => $data['timezone'] ?? $clinic?->timezone ?? 'Europe/London',
            'is_active' => $data['isActive'] ?? $clinic?->is_active ?? true,
            'address_json' => $data['address'] ?? $clinic?->address_json,
            'latitude' => $data['latitude'] ?? $clinic?->latitude,
            'longitude' => $data['longitude'] ?? $clinic?->longitude,
        ];
    }

    private function clinicFor(Request $request, int $id): Clinic
    {
        $clinic = Clinic::findOrFail($id);
        BranchScope::assert($request->user(), $clinic->id);

        return $clinic;
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Superadmin required to create or delete branches.');
        }
    }
}

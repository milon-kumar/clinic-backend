<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicService;
use App\Models\Service;
use App\Models\ServiceBenefit;
use App\Models\ServiceFaq;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::query()
            ->with(['packages', 'benefits', 'faqs', 'clinicServices.clinic'])
            ->orderBy('name')
            ->get()
            ->map->toApi()
            ->values();

        return response()->json(['data' => $services]);
    }

    public function show(int $id): JsonResponse
    {
        $service = Service::query()->with(['packages', 'benefits', 'faqs', 'clinicServices.clinic'])->findOrFail($id);

        return response()->json(['data' => $service->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $data = $this->validated($request);
        $service = Service::create($this->serviceAttrs($data));
        $this->syncNested($service, $data);
        $this->syncClinics($service, $data, creating: true);

        return response()->json([
            'data' => $service->fresh(['packages', 'benefits', 'faqs', 'clinicServices.clinic'])->toApi(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        $service = Service::findOrFail($id);
        $data = $this->validated($request, $service->id);
        $service->update($this->serviceAttrs($data));
        $this->syncNested($service, $data);
        $this->syncClinics($service, $data, creating: false);

        return response()->json([
            'data' => $service->fresh(['packages', 'benefits', 'faqs', 'clinicServices.clinic'])->toApi(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertOrgAdmin($request);
        Service::findOrFail($id)->delete();

        return response()->json(['message' => 'Service deleted']);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store('services', 'public');

        return response()->json([
            'path' => '/storage/'.$path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null): array
    {
        $slugRule = $id ? 'unique:services,slug,'.$id : 'unique:services,slug';
        $skuRule = $id ? 'unique:services,sku,'.$id : 'unique:services,sku';

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', $slugRule],
            'sku' => ['nullable', 'string', 'max:80', $skuRule],
            'category' => ['nullable', 'string', 'max:80'],
            'treatmentType' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'durationMinutes' => ['nullable', 'integer', 'min:10'],
            'basePricePence' => ['nullable', 'integer', 'min:0'],
            'appointmentAmountPence' => ['nullable', 'integer', 'min:0'],
            'appointmentAmount' => ['nullable', 'numeric', 'min:0'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'string'],
            'packages' => ['nullable', 'array'],
            'benefits' => ['nullable', 'array'],
            'faqs' => ['nullable', 'array'],
            'isActive' => ['nullable', 'boolean'],
            'isFeatured' => ['nullable', 'boolean'],
            'supportsBuy' => ['nullable', 'boolean'],
            'supportsBook' => ['nullable', 'boolean'],
            'offerAtAllClinics' => ['nullable', 'boolean'],
            'clinicIds' => ['nullable', 'array'],
            'clinicIds.*' => ['integer', 'exists:clinics,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function serviceAttrs(array $data): array
    {
        $name = $data['name'];
        $slug = $data['slug'] ?? Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'sku' => $data['sku'] ?? strtoupper(str_replace('-', '_', $slug)),
            'category' => $data['category'] ?? null,
            'treatment_type' => $data['treatmentType'] ?? null,
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['durationMinutes'] ?? 60,
            'base_price_pence' => $data['basePricePence'] ?? 0,
            'appointment_amount_pence' => array_key_exists('appointmentAmountPence', $data)
                ? (int) $data['appointmentAmountPence']
                : (int) round(($data['appointmentAmount'] ?? 0) * 100),
            'images' => array_values(array_filter($data['images'] ?? [])),
            'is_active' => $data['isActive'] ?? true,
            'is_featured' => $data['isFeatured'] ?? false,
            'supports_buy' => $data['supportsBuy'] ?? true,
            'supports_book' => $data['supportsBook'] ?? true,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncNested(Service $service, array $data): void
    {
        if (array_key_exists('packages', $data)) {
            $service->packages()->delete();
            foreach ($data['packages'] ?? [] as $pkg) {
                ServicePackage::create([
                    'service_id' => $service->id,
                    'title' => $pkg['title'] ?? 'Package',
                    'sessions' => $pkg['sessions'] ?? 1,
                    'price_pence' => $pkg['pricePence'] ?? 0,
                ]);
            }
        }

        if (array_key_exists('benefits', $data)) {
            $service->benefits()->delete();
            foreach ($data['benefits'] ?? [] as $benefit) {
                if (empty($benefit['title'])) {
                    continue;
                }
                ServiceBenefit::create([
                    'service_id' => $service->id,
                    'title' => $benefit['title'],
                    'description' => $benefit['description'] ?? null,
                ]);
            }
        }

        if (array_key_exists('faqs', $data)) {
            $service->faqs()->delete();
            foreach ($data['faqs'] ?? [] as $faq) {
                if (empty($faq['question'])) {
                    continue;
                }
                ServiceFaq::create([
                    'service_id' => $service->id,
                    'question' => $faq['question'],
                    'answer' => $faq['answer'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncClinics(Service $service, array $data, bool $creating): void
    {
        $hasExplicit = array_key_exists('clinicIds', $data);

        if ($creating && ! $hasExplicit) {
            $ids = Clinic::query()->pluck('id')->all();
        } elseif (! empty($data['offerAtAllClinics'])) {
            $ids = Clinic::query()->pluck('id')->all();
        } elseif ($hasExplicit) {
            $ids = $data['clinicIds'] ?? [];
        } else {
            return;
        }

        $keep = [];
        foreach ($ids as $clinicId) {
            ClinicService::updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'service_id' => $service->id,
                ],
                [
                    'buy_enabled' => true,
                    'book_enabled' => true,
                    'online_buy_enabled' => true,
                ]
            );
            $keep[] = (int) $clinicId;
        }

        ClinicService::query()
            ->where('service_id', $service->id)
            ->whereNotIn('clinic_id', $keep ?: [0])
            ->delete();
    }

    private function assertOrgAdmin(Request $request): void
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Only a superadmin can add or edit treatments for all branches.');
        }
    }
}

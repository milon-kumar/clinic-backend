<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PrepaidPackage;
use App\Models\Service;
use App\Models\ServicePrerequisite;

class PrerequisiteService
{
    /**
     * @return array{satisfied: bool, missing: array<int, array<string, mixed>>}
     */
    public function check(int $customerId, int $serviceId, int $clinicId): array
    {
        $prerequisites = ServicePrerequisite::query()
            ->where('service_id', $serviceId)
            ->whereIn('rule', ['must_complete', 'must_purchase'])
            ->with('prerequisiteService')
            ->get();

        if ($prerequisites->isEmpty()) {
            return ['satisfied' => true, 'missing' => []];
        }

        $missing = [];

        foreach ($prerequisites as $prerequisite) {
            $satisfied = match ($prerequisite->rule) {
                'must_complete' => $this->hasCompletedTreatment($customerId, $prerequisite->prerequisite_service_id, $clinicId),
                'must_purchase' => $this->hasActivePackage($customerId, $prerequisite->prerequisite_service_id, $clinicId),
                default => true,
            };

            if (! $satisfied) {
                $missing[] = [
                    'serviceId' => $prerequisite->prerequisite_service_id,
                    'name' => $prerequisite->prerequisiteService?->name,
                    'rule' => $prerequisite->rule,
                ];
            }
        }

        return [
            'satisfied' => empty($missing),
            'missing' => $missing,
        ];
    }

    private function hasCompletedTreatment(int $customerId, int $serviceId, int $clinicId): bool
    {
        return Appointment::query()
            ->where('customer_id', $customerId)
            ->where('clinic_id', $clinicId)
            ->where('service_id', $serviceId)
            ->where('status', 'completed')
            ->exists();
    }

    private function hasActivePackage(int $customerId, int $serviceId, int $clinicId): bool
    {
        return PrepaidPackage::query()
            ->where('customer_id', $customerId)
            ->where('clinic_id', $clinicId)
            ->where('service_id', $serviceId)
            ->where('status', 'active')
            ->whereColumn('sessions_used', '<', 'sessions_total')
            ->exists();
    }

    public function getPrerequisiteTree(int $serviceId): array
    {
        $service = Service::with(['prerequisites.prerequisiteService'])->findOrFail($serviceId);

        return $service->prerequisites->map(fn ($p) => [
            'serviceId' => $p->prerequisite_service_id,
            'name' => $p->prerequisiteService?->name,
            'rule' => $p->rule,
        ])->all();
    }
}

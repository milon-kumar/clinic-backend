<?php

namespace App\Services;

use App\Models\ClinicService;
use App\Models\Service;
use Illuminate\Support\Collection;

class ClinicCatalogService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getServices(int $clinicId, string $mode = 'all'): Collection
    {
        $query = Service::query()
            ->where('is_active', true)
            ->with(['packages', 'benefits', 'faqs']);

        $services = $query->get();

        return $services->map(function (Service $service) use ($clinicId, $mode) {
            $availability = $this->isAvailable($clinicId, $service->id, $mode);
            $price = $this->resolvePrice($clinicId, $service->id, 1);

            return [
                'serviceId' => $service->id,
                'sku' => $service->sku,
                'slug' => $service->slug,
                'name' => $service->name,
                'category' => $service->category,
                'treatmentType' => $service->treatment_type,
                'description' => $service->description,
                'durationMinutes' => $service->duration_minutes,
                'buyEnabled' => $availability['buyEnabled'] ?? false,
                'bookEnabled' => $availability['bookEnabled'] ?? false,
                'available' => $availability['ok'],
                'unavailableReason' => $availability['reason'],
                'pricePence' => $price['unitPricePence'],
                'packages' => $service->packages,
                'benefits' => $service->benefits,
                'faqs' => $service->faqs,
            ];
        })->filter(function (array $offering) use ($mode) {
            if ($mode === 'buy') {
                return $offering['buyEnabled'];
            }
            if ($mode === 'book') {
                return $offering['bookEnabled'];
            }

            return true;
        })->values();
    }

    /**
     * @return array{ok: bool, reason: ?string, buyEnabled: bool, bookEnabled: bool}
     */
    public function isAvailable(int $clinicId, int $serviceId, string $mode = 'all'): array
    {
        $service = Service::find($serviceId);

        if (! $service || ! $service->is_active) {
            return [
                'ok' => false,
                'reason' => 'SERVICE_INACTIVE',
                'buyEnabled' => false,
                'bookEnabled' => false,
            ];
        }

        $clinicService = ClinicService::query()
            ->where('clinic_id', $clinicId)
            ->where('service_id', $serviceId)
            ->first();

        if (! $clinicService) {
            return [
                'ok' => false,
                'reason' => 'NOT_OFFERED_AT_CLINIC',
                'buyEnabled' => false,
                'bookEnabled' => false,
            ];
        }

        $now = now();
        if ($clinicService->effective_from && $now->lt($clinicService->effective_from)) {
            return [
                'ok' => false,
                'reason' => 'NOT_YET_EFFECTIVE',
                'buyEnabled' => false,
                'bookEnabled' => false,
            ];
        }

        if ($clinicService->effective_to && $now->gt($clinicService->effective_to)) {
            return [
                'ok' => false,
                'reason' => 'NO_LONGER_EFFECTIVE',
                'buyEnabled' => false,
                'bookEnabled' => false,
            ];
        }

        $buyEnabled = $service->supports_buy
            && $clinicService->buy_enabled
            && $clinicService->online_buy_enabled;
        $bookEnabled = $service->supports_book && $clinicService->book_enabled;

        $ok = match ($mode) {
            'buy' => $buyEnabled,
            'book' => $bookEnabled,
            default => $buyEnabled || $bookEnabled,
        };

        $reason = null;
        if (! $ok) {
            $reason = match ($mode) {
                'buy' => ! $clinicService->online_buy_enabled ? 'NOT_AVAILABLE_ONLINE' : 'NOT_OFFERED_AT_CLINIC',
                'book' => 'BOOKING_UNAVAILABLE',
                default => 'NOT_OFFERED_AT_CLINIC',
            };
        }

        return [
            'ok' => $ok,
            'reason' => $reason,
            'buyEnabled' => $buyEnabled,
            'bookEnabled' => $bookEnabled,
        ];
    }

    public function dropUnavailableLines(\App\Models\Cart $cart, int $clinicId): void
    {
        $cart->load('lines');
        $mode = $cart->cart_type === 'book' ? 'book' : 'buy';

        foreach ($cart->lines as $line) {
            $availability = $this->isAvailable($clinicId, $line->service_id, $mode);
            if (! $availability['ok']) {
                $line->delete();
            }
        }
    }

    /**
     * @return array{unitPricePence: int, subtotalPence: int, tierDiscountPercent: int}
     */
    public function resolvePrice(int $clinicId, int $serviceId, int $quantity): array
    {
        $service = Service::findOrFail($serviceId);

        $clinicService = ClinicService::query()
            ->where('clinic_id', $clinicId)
            ->where('service_id', $serviceId)
            ->first();

        $unitPrice = $clinicService?->price_pence ?? $service->base_price_pence;
        $tierDiscount = $this->tierDiscountPercent($quantity);

        $subtotal = $unitPrice * $quantity;
        $discountAmount = (int) round($subtotal * ($tierDiscount / 100));

        return [
            'unitPricePence' => $unitPrice,
            'subtotalPence' => $subtotal - $discountAmount,
            'tierDiscountPercent' => $tierDiscount,
        ];
    }

    public function tierDiscountPercent(int $quantity): int
    {
        return match (true) {
            $quantity >= 10 => 40,
            $quantity >= 6 => 30,
            $quantity >= 3 => 20,
            default => 0,
        };
    }
}

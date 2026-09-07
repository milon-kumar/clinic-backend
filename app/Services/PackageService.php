<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrepaidPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackageService
{
    /**
     * @return Collection<int, PrepaidPackage>
     */
    public function createFromOrder(Order $order): Collection
    {
        $order->load('lines.service');

        return DB::transaction(function () use ($order) {
            $packages = collect();

            foreach ($order->lines as $line) {
                $package = PrepaidPackage::create([
                    'customer_id' => $order->customer_id,
                    'clinic_id' => $order->clinic_id,
                    'service_id' => $line->service_id,
                    'order_id' => $order->id,
                    'sessions_total' => $line->quantity,
                    'sessions_used' => 0,
                    'status' => 'active',
                    'expires_at' => now()->addYear(),
                ]);

                $packages->push($package);
            }

            return $packages;
        });
    }

    public function redeem(string $packageId, int $appointmentId): PrepaidPackage
    {
        return DB::transaction(function () use ($packageId, $appointmentId) {
            $package = PrepaidPackage::query()->lockForUpdate()->findOrFail($packageId);

            if (! $package->isRedeemable()) {
                throw new \RuntimeException('Package is not redeemable');
            }

            $package->increment('sessions_used');

            if ($package->sessionsRemaining() <= 0) {
                $package->update(['status' => 'exhausted']);
            }

            return $package->fresh();
        });
    }

    /**
     * @return Collection<int, PrepaidPackage>
     */
    public function listRedeemable(int $customerId, int $clinicId): Collection
    {
        return PrepaidPackage::query()
            ->where('customer_id', $customerId)
            ->where('clinic_id', $clinicId)
            ->where('status', 'active')
            ->whereColumn('sessions_used', '<', 'sessions_total')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('service')
            ->get();
    }
}

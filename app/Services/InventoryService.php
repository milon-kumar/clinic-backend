<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;

class InventoryService
{
    public function consumeForOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing('lines');

        foreach ($order->lines as $line) {
            $item = InventoryItem::query()->firstOrCreate(
                [
                    'clinic_id' => $order->clinic_id,
                    'service_id' => $line->service_id,
                ],
                [
                    'quantity_on_hand' => 0,
                    'reorder_level' => 5,
                    'unit' => 'session',
                ]
            );

            $item->decrement('quantity_on_hand', $line->quantity);

            StockMovement::create([
                'clinic_id' => $order->clinic_id,
                'service_id' => $line->service_id,
                'order_id' => $order->id,
                'created_by' => $actor?->id,
                'type' => 'sale',
                'quantity' => -1 * $line->quantity,
                'reference' => 'ORDER-'.$order->id,
                'notes' => 'Prepaid package sale',
            ]);
        }
    }

    public function restock(
        int $clinicId,
        int $serviceId,
        int $quantity,
        ?User $actor = null,
        array $meta = [],
    ): InventoryItem {
        $item = InventoryItem::query()->firstOrCreate(
            [
                'clinic_id' => $clinicId,
                'service_id' => $serviceId,
            ],
            [
                'quantity_on_hand' => 0,
                'reorder_level' => $meta['reorderLevel'] ?? 5,
                'unit' => $meta['unit'] ?? 'session',
            ]
        );

        $item->increment('quantity_on_hand', $quantity);
        if (isset($meta['reorderLevel'])) {
            $item->update(['reorder_level' => $meta['reorderLevel']]);
        }

        StockMovement::create([
            'clinic_id' => $clinicId,
            'service_id' => $serviceId,
            'supplier_id' => $meta['supplierId'] ?? null,
            'created_by' => $actor?->id,
            'type' => $meta['type'] ?? 'restock',
            'quantity' => $quantity,
            'unit_cost_pence' => $meta['unitCostPence'] ?? null,
            'reference' => $meta['reference'] ?? null,
            'notes' => $meta['notes'] ?? null,
        ]);

        return $item->fresh(['clinic', 'service']);
    }
}

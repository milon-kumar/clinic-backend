<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

class InvoiceService
{
    public function issueForOrder(Order $order): Invoice
    {
        $existing = Invoice::query()->where('order_id', $order->id)->first();
        if ($existing) {
            return $existing->load(['clinic', 'customer', 'order.lines.service']);
        }

        $order->loadMissing(['clinic', 'customer', 'lines.service']);

        $invoice = Invoice::create([
            'number' => $this->nextNumber($order),
            'order_id' => $order->id,
            'clinic_id' => $order->clinic_id,
            'customer_id' => $order->customer_id,
            'status' => 'issued',
            'subtotal_pence' => $order->subtotal_pence,
            'discount_pence' => $order->discount_pence,
            'total_pence' => $order->total_pence,
            'issued_at' => now(),
        ]);

        return $invoice->load(['clinic', 'customer', 'order.lines.service']);
    }

    private function nextNumber(Order $order): string
    {
        $prefix = 'ELX-'.strtoupper($order->clinic?->code ?? 'CLN').'-'.now()->format('Ym');
        $count = Invoice::query()->where('number', 'like', $prefix.'%')->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}

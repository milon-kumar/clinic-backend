<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['clinic', 'customer', 'order.lines.service'])
            ->orderByDesc('id');

        BranchScope::apply($query, $request->user());

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->where('clinic_id', $clinicId);
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()
            ->with(['clinic', 'customer', 'order.lines.service'])
            ->findOrFail($id);
        BranchScope::assert($request->user(), (int) $invoice->clinic_id);

        return response()->json(['data' => $invoice->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orderId' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::query()->with(['clinic', 'customer', 'lines.service'])->findOrFail($data['orderId']);
        BranchScope::assert($request->user(), (int) $order->clinic_id);

        if ($order->status !== 'paid') {
            abort(422, 'Invoices can only be issued for paid orders.');
        }

        $invoice = $this->invoiceService->issueForOrder($order);

        return response()->json(['data' => $invoice->toApi()], 201);
    }
}

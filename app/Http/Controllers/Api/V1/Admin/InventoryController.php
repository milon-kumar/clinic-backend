<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse
    {
        $clinicId = $request->query('clinicId');
        if ($clinicId) {
            BranchScope::assert($request->user(), (int) $clinicId);
        }

        $query = InventoryItem::query()->with(['clinic', 'service'])->orderBy('id');
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        } else {
            BranchScope::apply($query, $request->user());
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }

    public function restock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinicId' => ['required', 'integer', 'exists:clinics,id'],
            'serviceId' => ['required', 'integer', 'exists:services,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reorderLevel' => ['nullable', 'integer', 'min:0'],
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unitCostPence' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        BranchScope::assert($request->user(), (int) $data['clinicId']);
        Service::findOrFail($data['serviceId']);

        $item = $this->inventoryService->restock(
            (int) $data['clinicId'],
            (int) $data['serviceId'],
            (int) $data['quantity'],
            $request->user(),
            [
                'reorderLevel' => $data['reorderLevel'] ?? null,
                'supplierId' => $data['supplierId'] ?? null,
                'unitCostPence' => $data['unitCostPence'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'type' => 'restock',
            ]
        );

        return response()->json(['data' => $item->toApi()], 201);
    }

    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::query()
            ->with(['clinic', 'service', 'supplier'])
            ->orderByDesc('id')
            ->limit(200);

        BranchScope::apply($query, $request->user());

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->where('clinic_id', $clinicId);
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }

    public function suppliers(): JsonResponse
    {
        $rows = Supplier::query()->orderBy('name')->get()->map->toApi()->values();

        return response()->json(['data' => $rows]);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only a superadmin can add suppliers.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::create($data + ['is_active' => true]);

        return response()->json(['data' => $supplier->toApi()], 201);
    }
}

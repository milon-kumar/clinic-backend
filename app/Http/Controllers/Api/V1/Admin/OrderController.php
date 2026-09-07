<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Support\BranchScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['customer', 'clinic', 'lines.service', 'invoice'])
            ->orderByDesc('id');

        BranchScope::apply($query, $request->user());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->where('clinic_id', $clinicId);
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::query()->with(['customer', 'clinic', 'lines.service', 'invoice'])->findOrFail($id);
        BranchScope::assert($request->user(), (int) $order->clinic_id);

        return response()->json(['data' => $order->toApi()]);
    }

    public function carts(Request $request): JsonResponse
    {
        $query = Cart::query()
            ->with(['customer', 'clinic', 'lines.service'])
            ->whereHas('lines')
            ->orderByDesc('updated_at');

        BranchScope::apply($query, $request->user());

        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($request->user(), (int) $clinicId);
            $query->where('clinic_id', $clinicId);
        }

        return response()->json(['data' => $query->get()->map->toApi()->values()]);
    }
}

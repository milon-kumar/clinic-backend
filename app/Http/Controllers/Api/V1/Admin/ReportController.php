<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicStaff;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Support\BranchScope;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $clinicQuery = Clinic::query()->orderBy('name');
        BranchScope::apply($clinicQuery, $user, 'id');
        $clinics = $clinicQuery->get();
        $clinicIds = $clinics->pluck('id')->all();

        $appointmentQuery = Appointment::query();
        $orderQuery = Order::query()->where('status', 'paid');
        if (! $user->isSuperAdmin()) {
            $appointmentQuery->whereIn('clinic_id', $clinicIds ?: [0]);
            $orderQuery->whereIn('clinic_id', $clinicIds ?: [0]);
        }

        $staffQuery = User::query()->whereIn('role', Roles::staff());
        if (! $user->isSuperAdmin()) {
            $staffQuery->where(function ($q) use ($clinicIds) {
                $q->whereIn('clinic_id', $clinicIds ?: [0])
                    ->orWhereHas('staffAssignments', fn ($s) => $s->whereIn('clinic_id', $clinicIds ?: [0]));
            });
        }

        $clinicRows = $clinics->map(function (Clinic $clinic) use ($request) {
            $paid = Order::query()->where('status', 'paid')->where('clinic_id', $clinic->id);
            if ($from = $request->query('from')) {
                $paid->whereDate('paid_at', '>=', $from);
            }
            if ($to = $request->query('to')) {
                $paid->whereDate('paid_at', '<=', $to);
            }

            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'appointments' => Appointment::where('clinic_id', $clinic->id)->count(),
                'confirmed' => Appointment::where('clinic_id', $clinic->id)->where('status', 'confirmed')->count(),
                'staff' => ClinicStaff::where('clinic_id', $clinic->id)->where('is_active', true)->count(),
                'paidOrders' => (clone $paid)->count(),
                'revenuePence' => (int) (clone $paid)->sum('total_pence'),
            ];
        });

        $sheetQuery = Order::query()
            ->with(['clinic', 'customer', 'invoice'])
            ->where('status', 'paid')
            ->orderByDesc('paid_at');
        if (! $user->isSuperAdmin()) {
            $sheetQuery->whereIn('clinic_id', $clinicIds ?: [0]);
        }
        if ($from = $request->query('from')) {
            $sheetQuery->whereDate('paid_at', '>=', $from);
            $orderQuery->whereDate('paid_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $sheetQuery->whereDate('paid_at', '<=', $to);
            $orderQuery->whereDate('paid_at', '<=', $to);
        }
        if ($clinicId = $request->query('clinicId')) {
            BranchScope::assert($user, (int) $clinicId);
            $sheetQuery->where('clinic_id', $clinicId);
            $orderQuery->where('clinic_id', $clinicId);
        }

        $sheet = $sheetQuery->limit(500)->get()->map(fn (Order $order) => [
            'orderId' => $order->id,
            'invoiceNumber' => $order->invoice?->number,
            'paidAt' => $order->paid_at?->toDateString(),
            'clinicId' => $order->clinic_id,
            'clinicName' => $order->clinic?->name,
            'customerName' => trim(($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? '')),
            'paymentMethod' => $order->payment_method,
            'subtotalPence' => (int) $order->subtotal_pence,
            'discountPence' => (int) $order->discount_pence,
            'totalPence' => (int) $order->total_pence,
        ]);

        return response()->json([
            'data' => [
                'appointments' => [
                    'total' => (clone $appointmentQuery)->count(),
                    'pending' => (clone $appointmentQuery)->where('status', 'pending')->count(),
                    'confirmed' => (clone $appointmentQuery)->where('status', 'confirmed')->count(),
                    'cancelled' => (clone $appointmentQuery)->where('status', 'cancelled')->count(),
                    'completed' => (clone $appointmentQuery)->where('status', 'completed')->count(),
                ],
                'users' => [
                    'total' => User::count(),
                    'patients' => User::where('role', Roles::PATIENT)->count(),
                    'staff' => $staffQuery->count(),
                ],
                'services' => [
                    'total' => Service::count(),
                    'active' => Service::where('is_active', true)->count(),
                ],
                'orders' => [
                    'paidCount' => (clone $orderQuery)->count(),
                    'revenuePence' => (int) (clone $orderQuery)->sum('total_pence'),
                ],
                'clinics' => $clinicRows,
                'sheet' => $sheet,
            ],
        ]);
    }
}

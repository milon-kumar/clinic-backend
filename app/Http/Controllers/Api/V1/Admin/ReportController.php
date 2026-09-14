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
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = $request->query('from');
        $to = $request->query('to');
        $clinicId = $request->query('clinicId');

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

        if ($clinicId) {
            BranchScope::assert($user, (int) $clinicId);
            $appointmentQuery->where('clinic_id', $clinicId);
            $orderQuery->where('clinic_id', $clinicId);
        }

        if ($from) {
            $appointmentQuery->whereDate('appointment_date', '>=', $from);
            $orderQuery->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $appointmentQuery->whereDate('appointment_date', '<=', $to);
            $orderQuery->whereDate('paid_at', '<=', $to);
        }

        $staffQuery = User::query()->whereIn('role', Roles::staff());
        if (! $user->isSuperAdmin()) {
            $staffQuery->where(function ($q) use ($clinicIds) {
                $q->whereIn('clinic_id', $clinicIds ?: [0])
                    ->orWhereHas('staffAssignments', fn ($s) => $s->whereIn('clinic_id', $clinicIds ?: [0]));
            });
        }

        $clinicRows = $clinics->map(function (Clinic $clinic) use ($from, $to) {
            $appointments = Appointment::query()->where('clinic_id', $clinic->id);
            $paid = Order::query()->where('status', 'paid')->where('clinic_id', $clinic->id);
            if ($from) {
                $appointments->whereDate('appointment_date', '>=', $from);
                $paid->whereDate('paid_at', '>=', $from);
            }
            if ($to) {
                $appointments->whereDate('appointment_date', '<=', $to);
                $paid->whereDate('paid_at', '<=', $to);
            }

            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'appointments' => (clone $appointments)->count(),
                'confirmed' => (clone $appointments)->where('status', 'confirmed')->count(),
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
        if ($from) {
            $sheetQuery->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $sheetQuery->whereDate('paid_at', '<=', $to);
        }
        if ($clinicId) {
            $sheetQuery->where('clinic_id', $clinicId);
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

        $dailyFrom = $from ?: now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $dailyTo = $to ?: now()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $daily = $this->dailyRows($appointmentQuery, $orderQuery, $dailyFrom, $dailyTo);

        return response()->json([
            'data' => [
                'from' => $from,
                'to' => $to,
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
                'daily' => $daily,
                'sheet' => $sheet,
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyRows($appointmentQuery, $orderQuery, string $from, string $to): array
    {
        $appointmentCounts = (clone $appointmentQuery)
            ->selectRaw('DATE(appointment_date) as day, COUNT(*) as total')
            ->whereDate('appointment_date', '>=', $from)
            ->whereDate('appointment_date', '<=', $to)
            ->groupByRaw('DATE(appointment_date)')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->day)->toDateString() => (int) $row->total]);

        $orderDays = (clone $orderQuery)
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as total, COALESCE(SUM(total_pence), 0) as revenue')
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->groupByRaw('DATE(paid_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [Carbon::parse($row->day)->toDateString() => [
                'sellCount' => (int) $row->total,
                'revenuePence' => (int) $row->revenue,
            ]]);

        $rows = [];
        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->toDateString();
            $order = $orderDays->get($key, ['sellCount' => 0, 'revenuePence' => 0]);
            $rows[] = [
                'date' => $key,
                'appointmentCount' => (int) $appointmentCounts->get($key, 0),
                'sellCount' => (int) ($order['sellCount'] ?? 0),
                'revenuePence' => (int) ($order['revenuePence'] ?? 0),
            ];
        }

        return $rows;
    }
}

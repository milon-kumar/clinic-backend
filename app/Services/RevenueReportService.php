<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RevenueReportService
{
    /** @var array<string, int|null> */
    private array $sessionClinicCache = [];

    /**
     * @param  list<int>  $clinicIds
     */
    public function paidAppointmentQuery(array $clinicIds, bool $scoped, ?string $from, ?string $to): Builder
    {
        $query = Appointment::query()
            ->where('payment_status', 'paid')
            ->where('amount_pence', '>', 0);

        if ($scoped) {
            $query->whereIn('clinic_id', $clinicIds ?: [0]);
        }

        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @return array{paidCount: int, revenuePence: int}
     */
    public function paidAppointmentsSummary(Builder $query): array
    {
        return [
            'paidCount' => (int) (clone $query)->count(),
            'revenuePence' => (int) (clone $query)->sum('amount_pence'),
        ];
    }

    /**
     * @param  list<int>  $clinicIds
     * @return array{
     *     paidSessions: int,
     *     revenuePence: int,
     *     buyPence: int,
     *     bookPence: int
     * }
     */
    public function stripeSummary(
        array $clinicIds,
        bool $scoped,
        ?string $from,
        ?string $to,
        ?int $clinicId = null,
    ): array {
        $sessions = $this->stripeSessions($from, $to);

        if ($clinicId) {
            $sessions = $sessions->filter(fn (PaymentSession $session) => $this->sessionClinicId($session) === $clinicId);
        } elseif ($scoped) {
            $allowed = array_flip($clinicIds);
            $sessions = $sessions->filter(function (PaymentSession $session) use ($allowed) {
                $clinic = $this->sessionClinicId($session);

                return $clinic !== null && isset($allowed[$clinic]);
            });
        }

        return [
            'paidSessions' => $sessions->count(),
            'revenuePence' => (int) $sessions->sum('amount_pence'),
            'buyPence' => (int) $sessions->where('purpose', 'buy')->sum('amount_pence'),
            'bookPence' => (int) $sessions->where('purpose', 'book')->sum('amount_pence'),
        ];
    }

    /**
     * @return Collection<int, PaymentSession>
     */
    public function stripeSessions(?string $from, ?string $to): Collection
    {
        $query = PaymentSession::query()
            ->where('status', 'paid')
            ->where('id', 'like', 'cs_%')
            ->where('id', 'not like', 'cs_local_%');

        if ($from) {
            $query->whereDate('updated_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('updated_at', '<=', $to);
        }

        return $query->get();
    }

    /**
     * @return array<string, int>
     */
    public function stripeDailyPence(?string $from, ?string $to, ?int $clinicId = null, bool $scoped = false, array $clinicIds = []): array
    {
        $sessions = $this->stripeSessions($from, $to);

        if ($clinicId) {
            $sessions = $sessions->filter(fn (PaymentSession $session) => $this->sessionClinicId($session) === $clinicId);
        } elseif ($scoped) {
            $allowed = array_flip($clinicIds);
            $sessions = $sessions->filter(function (PaymentSession $session) use ($allowed) {
                $clinic = $this->sessionClinicId($session);

                return $clinic !== null && isset($allowed[$clinic]);
            });
        }

        $rows = [];
        foreach ($sessions as $session) {
            $day = $session->updated_at?->toDateString();
            if (! $day) {
                continue;
            }
            $rows[$day] = ($rows[$day] ?? 0) + (int) $session->amount_pence;
        }

        return $rows;
    }

    /**
     * @return array<string, array{paidCount: int, revenuePence: int}>
     */
    public function paidAppointmentDailyPence(Builder $appointmentQuery, string $from, string $to): array
    {
        $rows = (clone $appointmentQuery)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, COALESCE(SUM(amount_pence), 0) as revenue')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->day)->toDateString() => [
                    'paidCount' => (int) $row->total,
                    'revenuePence' => (int) $row->revenue,
                ],
            ]);

        return $rows->all();
    }

    public function sessionClinicId(PaymentSession $session): ?int
    {
        if (array_key_exists($session->id, $this->sessionClinicCache)) {
            return $this->sessionClinicCache[$session->id];
        }

        $payload = $session->payload ?? [];

        if (! empty($payload['clinicId'])) {
            return $this->sessionClinicCache[$session->id] = (int) $payload['clinicId'];
        }

        if (! empty($payload['orderId'])) {
            $clinicId = Order::query()->whereKey((int) $payload['orderId'])->value('clinic_id');

            return $this->sessionClinicCache[$session->id] = $clinicId ? (int) $clinicId : null;
        }

        if (! empty($payload['appointmentId'])) {
            $clinicId = Appointment::query()->whereKey((int) $payload['appointmentId'])->value('clinic_id');

            return $this->sessionClinicCache[$session->id] = $clinicId ? (int) $clinicId : null;
        }

        if (! empty($payload['cartId'])) {
            $clinicId = Cart::query()->whereKey((int) $payload['cartId'])->value('clinic_id');

            return $this->sessionClinicCache[$session->id] = $clinicId ? (int) $clinicId : null;
        }

        return $this->sessionClinicCache[$session->id] = null;
    }
}

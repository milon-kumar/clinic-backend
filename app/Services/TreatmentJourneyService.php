<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PrepaidPackage;
use App\Models\User;
use App\Support\BranchScope;
use Illuminate\Support\Collection;

class TreatmentJourneyService
{
    public function previousAppointment(Appointment $appointment): ?Appointment
    {
        if ($appointment->relationLoaded('previousAppointment') && $appointment->previousAppointment) {
            return $appointment->previousAppointment;
        }

        $linked = Appointment::query()
            ->where('next_appointment_id', $appointment->id)
            ->first();

        if ($linked) {
            return $linked;
        }

        $query = Appointment::query()
            ->where('customer_id', $appointment->customer_id)
            ->where('service_id', $appointment->service_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', '!=', 'cancelled');

        if ($appointment->package_id) {
            $query->where('package_id', $appointment->package_id);
        }

        return $query
            ->where(function ($q) use ($appointment) {
                $date = $appointment->appointment_date?->toDateString();
                $time = $appointment->appointment_time ?: '00:00';

                $q->whereDate('appointment_date', '<', $date)
                    ->orWhere(function ($inner) use ($date, $time) {
                        $inner->whereDate('appointment_date', $date)
                            ->where('appointment_time', '<', $time);
                    });
            })
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->first();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function journeyAppointments(Appointment $appointment): Collection
    {
        $query = Appointment::query()
            ->where('customer_id', $appointment->customer_id)
            ->where('service_id', $appointment->service_id)
            ->where('status', '!=', 'cancelled');

        if ($appointment->package_id) {
            $query->where('package_id', $appointment->package_id);
        }

        return $query
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();
    }

    public function decorate(Appointment $appointment): array
    {
        $appointment->loadMissing(['clinic', 'service', 'nextAppointment', 'prepaidPackage', 'previousAppointment']);

        $siblings = $this->journeyAppointments($appointment);
        $number = 1;
        foreach ($siblings->values() as $index => $row) {
            if ((int) $row->id === (int) $appointment->id) {
                $number = $index + 1;
                break;
            }
        }

        $previous = $this->previousAppointment($appointment);
        $payload = $appointment->toApi();
        $payload['sessionNotes'] = $appointment->session_notes;
        $payload['previousSessionNotes'] = $previous?->session_notes;
        $payload['previousAppointmentId'] = $previous?->id;
        $payload['sessionNumber'] = $number;
        $payload['sessionsInJourney'] = $siblings->count();
        $payload['sessionsTotal'] = $appointment->prepaidPackage?->sessions_total;
        $payload['sessionsUsed'] = $appointment->prepaidPackage?->sessions_used;
        $payload['sessionsRemaining'] = $appointment->prepaidPackage?->sessionsRemaining();

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForAdmin(User $staff, ?string $search = null, ?int $clinicId = null): array
    {
        $query = PrepaidPackage::query()->with(['clinic', 'service', 'customer', 'order.lines']);
        BranchScope::apply($query, $staff);

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('customer', function ($customer) use ($term) {
                    $customer->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                })->orWhereHas('service', function ($service) use ($term) {
                    $service->where('name', 'like', $term);
                })->orWhereHas('clinic', function ($clinic) use ($term) {
                    $clinic->where('name', 'like', $term);
                });
            });
        }

        $packages = $query->orderByDesc('id')
            ->get()
            ->map(fn (PrepaidPackage $package) => $this->packageJourney($package))
            ->values()
            ->all();

        return collect(array_merge(
            $packages,
            $this->standaloneJourneys($staff, $search, $clinicId),
        ))
            ->sortByDesc(fn (array $row) => $row['bookingDate'] ?? '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function findForAdmin(User $staff, string $id): array
    {
        if (ctype_digit($id)) {
            $query = PrepaidPackage::query()->with(['clinic', 'service', 'customer', 'order.lines']);
            BranchScope::apply($query, $staff);
            $package = $query->find((int) $id);
            if ($package) {
                return $this->packageJourney($package);
            }
        }

        foreach ($this->standaloneJourneys($staff) as $row) {
            if ((string) $row['id'] === $id) {
                return $row;
            }
        }

        abort(404, 'Treatment order not found');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function packagesForCustomer(User $user, ?int $clinicId = null): array
    {
        $query = $user->prepaidPackages()->with(['clinic', 'service'])->orderByDesc('id');

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        $packages = $query->get()
            ->map(fn (PrepaidPackage $package) => $this->packageJourney($package))
            ->values()
            ->all();

        return array_values(array_merge(
            $packages,
            $this->standaloneJourneys(null, null, $clinicId, $user->id),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function packageJourney(PrepaidPackage $package): array
    {
        $package->loadMissing(['clinic', 'service', 'customer', 'order.lines']);

        $appointments = Appointment::query()
            ->where('package_id', $package->id)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get();

        $sessions = $appointments->values()->map(function (Appointment $appointment, int $index) use ($appointments) {
            $previous = $index > 0 ? $appointments[$index - 1] : null;

            return $this->sessionRow($appointment, $index + 1, $previous?->session_notes);
        })->all();

        $completed = $appointments->where('status', 'completed')->last();
        $pricePence = $this->packagePricePence($package, $appointments);

        return array_merge($package->toApi(), $this->patientFields($package), [
            'lastSessionNotes' => $completed?->session_notes,
            'sessions' => $sessions,
            'orderId' => $package->order_id,
            'pricePence' => $pricePence,
            'price' => $pricePence / 100,
            'bookingDate' => $appointments->first()?->appointment_date?->toDateString()
                ?? $package->order?->paid_at?->toDateString()
                ?? $package->created_at?->toDateString(),
        ]);
    }

    /**
     * @return array{treatmentsRemaining: int, packages: array<int, array<string, mixed>>, standaloneTreatments: array<int, array<string, mixed>>}
     */
    public function forCustomer(User $user): array
    {
        $packages = $user->prepaidPackages()
            ->with(['clinic', 'service'])
            ->orderByDesc('id')
            ->get();

        $packageIds = $packages->pluck('id');
        $journeys = $packages->map(fn (PrepaidPackage $package) => $this->packageJourney($package))->values()->all();

        $standalone = Appointment::query()
            ->where('customer_id', $user->id)
            ->where(function ($q) use ($packageIds) {
                $q->whereNull('package_id');
                if ($packageIds->isNotEmpty()) {
                    $q->orWhereNotIn('package_id', $packageIds);
                }
            })
            ->where('status', '!=', 'cancelled')
            ->with(['clinic', 'service'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get()
            ->groupBy('service_id');

        $standaloneTreatments = [];
        foreach ($standalone as $appointments) {
            $sorted = $appointments->values();
            $first = $sorted->first();
            $completed = $sorted->where('status', 'completed')->count();
            $upcoming = $sorted->whereNotIn('status', ['completed', 'cancelled'])->count();

            $standaloneTreatments[] = [
                'id' => 'service-'.$first->service_id,
                'clinicId' => $first->clinic_id,
                'clinicName' => $first->clinic?->name,
                'serviceId' => $first->service_id,
                'serviceName' => $first->service?->name,
                'sessionsTotal' => $sorted->count(),
                'sessionsUsed' => $completed,
                'sessionsRemaining' => $upcoming,
                'status' => $upcoming > 0 ? 'active' : 'completed',
                'lastSessionNotes' => $sorted->where('status', 'completed')->last()?->session_notes,
                'sessions' => $sorted->map(function (Appointment $appointment, int $index) use ($sorted) {
                    $previous = $index > 0 ? $sorted[$index - 1] : null;

                    return $this->sessionRow($appointment, $index + 1, $previous?->session_notes);
                })->all(),
            ];
        }

        return [
            'treatmentsRemaining' => $packages->sum(fn (PrepaidPackage $package) => $package->sessionsRemaining()),
            'packages' => $journeys,
            'standaloneTreatments' => $standaloneTreatments,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function standaloneJourneys(?User $staff, ?string $search = null, ?int $clinicId = null, ?int $customerId = null): array
    {
        $query = Appointment::query()
            ->with(['clinic', 'service', 'customer'])
            ->where(function ($q) {
                $q->whereNull('package_id')->orWhere('package_id', 0);
            })
            ->where('status', '!=', 'cancelled');

        if ($staff) {
            BranchScope::apply($query, $staff);
        }

        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhereHas('customer', function ($customer) use ($term) {
                        $customer->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    })
                    ->orWhereHas('service', fn ($service) => $service->where('name', 'like', $term))
                    ->orWhereHas('clinic', fn ($clinic) => $clinic->where('name', 'like', $term));
            });
        }

        $grouped = $query
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->get()
            ->groupBy(function (Appointment $appointment) {
                $who = $appointment->customer_id
                    ?: 'email:'.strtolower((string) ($appointment->email ?: $appointment->full_name));

                return $who.'-'.$appointment->service_id.'-'.$appointment->clinic_id;
            });

        $rows = [];
        foreach ($grouped as $items) {
            $sorted = $items->values();
            $first = $sorted->first();
            $completed = $sorted->where('status', 'completed')->count();
            $upcoming = $sorted->whereNotIn('status', ['completed', 'cancelled'])->count();
            $patientName = trim(($first->customer?->first_name ?? '').' '.($first->customer?->last_name ?? ''))
                ?: ($first->full_name ?: $first->customer?->name ?: 'Unknown patient');

            $pricePence = (int) $sorted->sum('amount_pence');
            $rows[] = [
                'id' => 'standalone-'.$first->clinic_id.'-'.$first->service_id.'-'.($first->customer_id ?: md5($patientName)),
                'customerId' => $first->customer_id,
                'patientName' => $patientName,
                'patientEmail' => $first->customer?->email ?: $first->email,
                'patientPhone' => $first->customer?->phone ?: $first->phone,
                'clinicId' => $first->clinic_id,
                'clinicName' => $first->clinic?->name,
                'serviceId' => $first->service_id,
                'serviceName' => $first->service?->name,
                'sessionsTotal' => $sorted->count(),
                'sessionsUsed' => $completed,
                'sessionsRemaining' => $upcoming,
                'status' => $upcoming > 0 ? 'active' : 'completed',
                'lastSessionNotes' => $sorted->where('status', 'completed')->last()?->session_notes,
                'pricePence' => $pricePence,
                'price' => $pricePence / 100,
                'bookingDate' => $first->appointment_date?->toDateString(),
                'sessions' => $sorted->map(function (Appointment $appointment, int $index) use ($sorted) {
                    $previous = $index > 0 ? $sorted[$index - 1] : null;

                    return $this->sessionRow($appointment, $index + 1, $previous?->session_notes);
                })->all(),
            ];
        }

        return $rows;
    }

    /**
     * @return array{customerId: int|null, patientName: string, patientEmail: string|null, patientPhone: string|null}
     */
    private function patientFields(PrepaidPackage $package): array
    {
        return [
            'customerId' => $package->customer_id,
            'patientName' => trim(($package->customer?->first_name ?? '').' '.($package->customer?->last_name ?? ''))
                ?: ($package->customer?->name ?: 'Unknown patient'),
            'patientEmail' => $package->customer?->email,
            'patientPhone' => $package->customer?->phone,
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     */
    private function packagePricePence(PrepaidPackage $package, Collection $appointments): int
    {
        $order = $package->order;
        if ($order) {
            $order->loadMissing('lines');
            $line = $order->lines->firstWhere('service_id', $package->service_id);
            if ($line) {
                return (int) $line->quantity * (int) $line->unit_price_pence;
            }

            return (int) $order->total_pence;
        }

        return (int) $appointments->sum('amount_pence');
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(Appointment $appointment, int $number, ?string $previousNotes): array
    {
        return [
            'id' => $appointment->id,
            'sessionNumber' => $number,
            'appointmentDate' => $appointment->appointment_date?->toDateString(),
            'appointmentTime' => $appointment->appointment_time,
            'status' => $appointment->status,
            'sessionNotes' => $appointment->session_notes,
            'previousSessionNotes' => $previousNotes,
            'notes' => $appointment->notes,
        ];
    }
}

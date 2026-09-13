<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Service;
use App\Models\User;
use App\Support\Roles;
use App\Support\UserMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalTreatmentService
{
    public function __construct(
        private ClinicCatalogService $catalog,
        private PackageService $packages,
        private TreatmentJourneyService $journeys,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(User $staff, array $data): array
    {
        $service = Service::query()->with('packages')->findOrFail((int) $data['serviceId']);
        if (! $service->allow_local) {
            abort(422, 'This treatment is not allowed as a local treatment.');
        }

        $clinicId = (int) $data['clinicId'];
        $sessions = max(1, min(20, (int) ($data['sessions'] ?? 1)));
        $priced = $this->catalog->resolvePrice($clinicId, $service->id, $sessions);
        $totalPence = array_key_exists('pricePence', $data)
            ? (int) $data['pricePence']
            : (array_key_exists('price', $data)
                ? (int) round(((float) $data['price']) * 100)
                : (int) $priced['subtotalPence']);
        $unitPence = $sessions > 0 ? (int) intdiv($totalPence + $sessions - 1, $sessions) : 0;

        $package = DB::transaction(function () use ($data, $service, $clinicId, $sessions, $priced, $totalPence, $unitPence) {
            $customer = $this->resolveCustomer($data, $clinicId);

            $order = Order::create([
                'customer_id' => $customer->id,
                'clinic_id' => $clinicId,
                'status' => 'paid',
                'subtotal_pence' => $priced['listSubtotalPence'],
                'discount_pence' => max(0, $priced['listSubtotalPence'] - $totalPence),
                'total_pence' => $totalPence,
                'payment_method' => $data['paymentMethod'] ?? 'cash',
                'paid_at' => now(),
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'service_id' => $service->id,
                'quantity' => $sessions,
                'unit_price_pence' => $unitPence,
            ]);

            $created = $this->packages->createFromOrder($order->load('lines.service'))->first();

            if (! empty($data['bookingDate'])) {
                Appointment::create([
                    'customer_id' => $customer->id,
                    'clinic_id' => $clinicId,
                    'service_id' => $service->id,
                    'package_id' => $created?->id,
                    'full_name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'appointment_date' => $data['bookingDate'],
                    'appointment_time' => $data['bookingTime'] ?? '10:00',
                    'status' => 'confirmed',
                    'amount_pence' => 0,
                    'payment_method' => 'package',
                    'payment_status' => 'package',
                    'qr_token' => (string) Str::uuid(),
                ]);
            }

            return $created?->fresh(['clinic', 'service', 'customer', 'order.lines']);
        });

        if (! $package) {
            abort(500, 'Could not import the treatment.');
        }

        $order = $package->order ?: Order::query()->find($package->order_id);
        if ($order) {
            try {
                $this->notifications->purchaseConfirmed($order);
            } catch (\Throwable) {
                // order still created
            }
        }

        return $this->journeys->findForAdmin($staff, (string) $package->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data, int $clinicId): User
    {
        if (! empty($data['customerId'])) {
            $user = User::query()->findOrFail((int) $data['customerId']);
            if ($user->role !== Roles::PATIENT) {
                abort(422, 'Select a customer account.');
            }

            return $user;
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            abort(422, 'Email is required to create a customer.');
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            if ($existing->role !== Roles::PATIENT) {
                abort(422, 'That email belongs to a staff account.');
            }

            return $existing;
        }

        $first = trim((string) ($data['firstName'] ?? ''));
        $last = trim((string) ($data['lastName'] ?? ''));
        if ($first === '' || $last === '') {
            abort(422, 'First and last name are required for a new customer.');
        }

        return User::create(array_merge(UserMapper::fromRegister([
            'firstName' => $first,
            'lastName' => $last,
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'username' => $this->uniqueUsername($email),
            'password' => Str::random(20),
        ]), [
            'role' => Roles::PATIENT,
            'is_verified' => true,
            'email_verified_at' => now(),
            'selected_clinic_id' => $clinicId,
        ]));
    }

    private function uniqueUsername(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'customer';
        $username = $base;
        $i = 1;
        while (User::query()->where('username', $username)->exists()) {
            $username = $base.$i;
            $i++;
        }

        return $username;
    }
}

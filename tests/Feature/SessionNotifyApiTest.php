<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmedMail;
use App\Mail\NextSessionMail;
use App\Mail\PurchaseConfirmedMail;
use App\Models\Appointment;
use App\Models\ClinicSchedule;
use App\Models\PrepaidPackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class SessionNotifyApiTest extends PlatformTestCase
{
    public function test_booking_confirm_sends_client_email(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'mail-book', 'code' => 'MAIL_BOOK']);
        $service = $this->createService(['duration_minutes' => 60]);
        $slotDate = now()->next(Carbon::WEDNESDAY)->setTime(10, 0);
        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        Sanctum::actingAs($user);
        $holdId = $this->postJson('/api/v1/book/holds', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'startsAt' => $slotDate->toIso8601String(),
        ])->json('data.holdId');

        $this->postJson('/api/v1/book/appointments/confirm', [
            'holdId' => $holdId,
            'fullName' => 'Jane Patient',
            'phone' => '555-0100',
            'email' => $user->email,
            'paymentMethod' => 'cash',
        ])->assertCreated();

        Mail::assertSent(BookingConfirmedMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_admin_can_complete_and_schedule_next_session(): void
    {
        Mail::fake();

        $admin = $this->createUser(['role' => 'superadmin']);
        $patient = $this->createUser(['email' => 'next@example.com']);
        $clinic = $this->createClinic(['slug' => 'mail-session', 'code' => 'MAIL_SESS']);
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        $package = PrepaidPackage::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 6,
            'sessions_used' => 1,
            'status' => 'active',
        ]);

        $appointment = Appointment::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'package_id' => $package->id,
            'full_name' => $patient->name,
            'email' => $patient->email,
            'phone' => '555-0101',
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'confirmed',
            'amount_pence' => 0,
            'qr_token' => 'qr-session-1',
        ]);

        Sanctum::actingAs($admin);
        $nextDate = now()->addDay()->toDateString();

        $this->patchJson('/api/v1/admin/appointments/'.$appointment->id.'/complete', [
            'action' => 'complete',
            'sessionNotes' => 'Skin slightly red. Use SPF daily.',
            'nextAppointmentDate' => $nextDate,
            'nextAppointmentTime' => '11:30',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sessionNotes', 'Skin slightly red. Use SPF daily.')
            ->assertJsonPath('next.appointmentDate', $nextDate)
            ->assertJsonPath('next.appointmentTime', '11:30')
            ->assertJsonPath('next.previousSessionNotes', 'Skin slightly red. Use SPF daily.')
            ->assertJsonPath('next.sessionNumber', 2)
            ->assertJsonPath('next.sessionsRemaining', 5);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('prepaid_packages', [
            'id' => $package->id,
            'next_appointment_date' => $nextDate,
            'next_appointment_time' => '11:30',
        ]);

        Mail::assertSent(NextSessionMail::class, fn ($mail) => $mail->hasTo($patient->email));
    }

    public function test_patient_packages_include_remaining_sessions_and_notes(): void
    {
        $patient = $this->createUser(['email' => 'journey@example.com']);
        $clinic = $this->createClinic(['slug' => 'journey', 'code' => 'JOURNEY']);
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);

        $package = PrepaidPackage::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'sessions_total' => 4,
            'sessions_used' => 1,
            'status' => 'active',
        ]);

        Appointment::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'package_id' => $package->id,
            'full_name' => $patient->name,
            'email' => $patient->email,
            'appointment_date' => now()->subWeek()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'completed',
            'session_notes' => 'Start on low setting.',
            'amount_pence' => 0,
            'qr_token' => 'qr-journey-1',
        ]);

        Sanctum::actingAs($patient);

        $this->getJson('/api/v1/customers/me/packages')
            ->assertOk()
            ->assertJsonPath('data.0.sessionsRemaining', 3)
            ->assertJsonPath('data.0.sessionsTotal', 4)
            ->assertJsonPath('data.0.lastSessionNotes', 'Start on low setting.')
            ->assertJsonPath('data.0.sessions.0.sessionNotes', 'Start on low setting.');

        Sanctum::actingAs($this->createUser(['role' => 'superadmin']));

        $this->getJson('/api/v1/admin/users/'.$patient->id)
            ->assertOk()
            ->assertJsonPath('data.treatmentJourneys.treatmentsRemaining', 3)
            ->assertJsonPath('data.treatmentJourneys.packages.0.lastSessionNotes', 'Start on low setting.');

        $this->getJson('/api/v1/admin/treatment-journeys?search=journey')
            ->assertOk()
            ->assertJsonPath('data.0.sessionsRemaining', 3)
            ->assertJsonPath('data.0.lastSessionNotes', 'Start on low setting.')
            ->assertJsonPath('data.0.patientEmail', 'journey@example.com')
            ->assertJsonPath('data.0.patientName', $patient->name)
            ->assertJsonPath('data.0.bookingDate', now()->subWeek()->toDateString());

        $this->getJson('/api/v1/admin/treatment-journeys/'.$package->id)
            ->assertOk()
            ->assertJsonPath('data.sessionsRemaining', 3)
            ->assertJsonPath('data.serviceName', $service->name)
            ->assertJsonPath('data.sessions.0.sessionNotes', 'Start on low setting.');
    }

    public function test_completed_cash_appointment_appears_in_treatment_journeys(): void
    {
        $admin = $this->createUser(['role' => 'superadmin']);
        $patient = $this->createUser(['email' => 'cash-journey@example.com', 'first_name' => 'Zakaria']);
        $clinic = $this->createClinic(['slug' => 'cash-journey', 'code' => 'CASH_J']);
        $service = $this->createService(['name' => 'Laser Hair Removal']);
        $this->attachServiceToClinic($clinic, $service);

        $appointment = Appointment::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'full_name' => 'MD Zakaria',
            'email' => $patient->email,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'confirmed',
            'amount_pence' => 29900,
            'payment_method' => 'cash',
            'qr_token' => 'qr-cash-1',
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/appointments/'.$appointment->id.'/complete', [
            'action' => 'complete',
            'sessionNotes' => 'First pass done.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $list = $this->getJson('/api/v1/admin/treatment-journeys?search=Zakaria')
            ->assertOk()
            ->assertJsonPath('data.0.serviceName', 'Laser Hair Removal')
            ->assertJsonPath('data.0.sessionsUsed', 1)
            ->assertJsonPath('data.0.lastSessionNotes', 'First pass done.')
            ->assertJsonPath('data.0.pricePence', 29900)
            ->assertJsonPath('data.0.bookingDate', now()->toDateString())
            ->json('data.0');

        $this->getJson('/api/v1/admin/treatment-journeys/'.$list['id'])
            ->assertOk()
            ->assertJsonPath('data.patientName', 'Zakaria User')
            ->assertJsonPath('data.sessionsRemaining', 0);
    }

    public function test_buy_confirm_sends_purchase_email(): void
    {
        Mail::fake();

        $user = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'mail-buy', 'code' => 'MAIL_BUY']);
        $service = $this->createService(['base_price_pence' => 10000]);
        $this->attachServiceToClinic($clinic, $service);

        $user->update(['selected_clinic_id' => $clinic->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 3,
            'clinicId' => $clinic->id,
        ])->assertOk();

        $this->postJson('/api/v1/buy/checkout/confirm')->assertOk();

        Mail::assertSent(PurchaseConfirmedMail::class, fn ($mail) => $mail->hasTo($user->email));
    }
}

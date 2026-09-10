<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\ClinicSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\PlatformTestCase;

class NotificationApiTest extends PlatformTestCase
{
    public function test_user_can_list_mark_and_clear_notifications(): void
    {
        $patient = $this->createUser();
        $admin = $this->createUser(['role' => 'superadmin', 'email' => 'ops-inbox@example.com']);

        $customerNote = AppNotification::create([
            'user_id' => $patient->id,
            'audience' => 'customer',
            'type' => 'booking_confirmed',
            'title' => 'Booking confirmed',
            'body' => 'Laser at Test Clinic.',
            'href' => '/account/appointments',
        ]);
        AppNotification::create([
            'user_id' => $admin->id,
            'audience' => 'staff',
            'type' => 'new_booking',
            'title' => 'New booking',
            'body' => 'Jane booked Laser.',
            'href' => '/Admin/appointments',
        ]);

        Sanctum::actingAs($patient);
        $this->getJson('/api/v1/notifications?audience=customer')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('data.0.type', 'booking_confirmed');

        $this->patchJson('/api/v1/notifications/'.$customerNote->id.'/read')->assertOk();
        $this->assertNotNull($customerNote->fresh()?->read_at);

        AppNotification::create([
            'user_id' => $patient->id,
            'audience' => 'customer',
            'type' => 'purchase_confirmed',
            'title' => 'Purchase confirmed',
            'href' => '/account/packages',
        ]);
        $this->postJson('/api/v1/notifications/read-all', ['audience' => 'customer'])->assertOk();
        $this->getJson('/api/v1/notifications/unread-count?audience=customer')
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 0);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/notifications?audience=staff')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'new_booking')
            ->assertJsonPath('unreadCount', 1);
    }

    public function test_booking_creates_customer_and_staff_notifications(): void
    {
        Mail::fake();

        $patient = $this->createUser();
        $admin = $this->createUser(['role' => 'superadmin', 'email' => 'ops@example.com']);
        $clinic = $this->createClinic(['slug' => 'note-book', 'code' => 'NOTE_BOOK']);
        $service = $this->createService(['duration_minutes' => 60]);
        $slotDate = now()->next(Carbon::THURSDAY)->setTime(11, 0);
        ClinicSchedule::create([
            'clinic_id' => $clinic->id,
            'day_of_week' => $slotDate->dayOfWeek,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'slot_interval_minutes' => 60,
        ]);

        Sanctum::actingAs($patient);
        $holdId = $this->postJson('/api/v1/book/holds', [
            'clinicId' => $clinic->id,
            'serviceId' => $service->id,
            'startsAt' => $slotDate->toIso8601String(),
        ])->json('data.holdId');

        $this->postJson('/api/v1/book/appointments/confirm', [
            'holdId' => $holdId,
            'fullName' => 'Jane Patient',
            'phone' => '555-0100',
            'email' => $patient->email,
            'paymentMethod' => 'cash',
        ])->assertCreated();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $patient->id,
            'audience' => 'customer',
            'type' => 'booking_confirmed',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'audience' => 'staff',
            'type' => 'new_booking',
        ]);
    }

    public function test_purchase_notifies_customer_and_staff(): void
    {
        Mail::fake();

        $patient = $this->createUser();
        $admin = $this->createUser(['role' => 'superadmin', 'email' => 'buy-ops@example.com']);
        $clinic = $this->createClinic(['slug' => 'note-buy', 'code' => 'NOTE_BUY']);
        $service = $this->createService(['base_price_pence' => 10000]);
        $this->attachServiceToClinic($clinic, $service);
        $patient->update(['selected_clinic_id' => $clinic->id]);

        Sanctum::actingAs($patient);
        $this->postJson('/api/v1/cart/lines', [
            'serviceId' => $service->id,
            'quantity' => 3,
            'clinicId' => $clinic->id,
        ])->assertOk();
        $this->postJson('/api/v1/buy/checkout/confirm')->assertOk();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $patient->id,
            'type' => 'purchase_confirmed',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'new_order',
        ]);
    }
}

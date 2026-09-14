<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Review;
use Tests\PlatformTestCase;

class ReviewApiTest extends PlatformTestCase
{
    public function test_customer_can_submit_review_and_admin_can_publish(): void
    {
        $patient = $this->createUser(['first_name' => 'Jane', 'last_name' => 'Doe', 'name' => 'Jane Doe']);
        $admin = $this->createUser(['role' => 'superadmin', 'email' => 'review-ops@example.com']);
        $clinic = $this->createClinic(['slug' => 'review-clinic', 'code' => 'REV_CLINIC']);
        $service = $this->createService(['name' => 'Laser Glow']);
        $this->attachServiceToClinic($clinic, $service);

        $appointment = Appointment::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'full_name' => $patient->name,
            'email' => $patient->email,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00',
            'status' => 'completed',
            'amount_pence' => 0,
            'qr_token' => 'qr-review-1',
        ]);

        $this->actingAsUser($patient);
        $this->postJson('/api/v1/customers/me/reviews', [
            'rating' => 5,
            'title' => 'Loved it',
            'body' => 'The laser was gentle and the clinic was spotless.',
            'appointmentId' => $appointment->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rating', 5);

        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/customers/me/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.serviceName', 'Laser Glow');

        $this->postJson('/api/v1/customers/me/reviews', [
            'rating' => 4,
            'body' => 'Trying to review the same visit twice.',
            'appointmentId' => $appointment->id,
        ])->assertUnprocessable();

        $review = Review::query()->first();
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'new_review',
        ]);

        $this->actingAsUser($admin);
        $this->getJson('/api/v1/admin/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customerEmail', $patient->email);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id, [
            'status' => 'published',
            'adminReply' => 'Thank you Jane — we are glad you felt looked after.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.adminReply', 'Thank you Jane — we are glad you felt looked after.');

        $this->getJson('/api/v1/reviews?serviceId='.$service->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customerName', 'Jane D.')
            ->assertJsonPath('data.0.rating', 5);

        $this->getJson('/api/v1/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('data.ratingAvg', 5)
            ->assertJsonPath('data.ratingCount', 1);
    }

    public function test_incomplete_visit_cannot_be_reviewed(): void
    {
        $patient = $this->createUser();
        $clinic = $this->createClinic(['slug' => 'review-open', 'code' => 'REV_OPEN']);
        $service = $this->createService();
        $this->attachServiceToClinic($clinic, $service);
        $appointment = Appointment::create([
            'customer_id' => $patient->id,
            'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'full_name' => $patient->name,
            'email' => $patient->email,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '11:00',
            'status' => 'confirmed',
            'amount_pence' => 0,
            'qr_token' => 'qr-review-open',
        ]);

        $this->actingAsUser($patient);
        $this->postJson('/api/v1/customers/me/reviews', [
            'rating' => 5,
            'body' => 'Too soon.',
            'appointmentId' => $appointment->id,
        ])->assertUnprocessable();
    }

    public function test_patient_cannot_moderate_reviews(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'patient']));
        $this->getJson('/api/v1/admin/reviews')->assertForbidden();
    }

    public function test_admin_can_hide_and_delete_reviews(): void
    {
        $patient = $this->createUser();
        $admin = $this->createUser(['role' => 'admin']);
        $review = Review::create([
            'user_id' => $patient->id,
            'rating' => 2,
            'body' => 'Not for us.',
            'status' => Review::STATUS_PUBLISHED,
        ]);

        $this->actingAsUser($admin);
        $this->patchJson('/api/v1/admin/reviews/'.$review->id, ['status' => 'hidden'])
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden');

        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonCount(0, 'data');

        $this->deleteJson('/api/v1/admin/reviews/'.$review->id)->assertOk();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}

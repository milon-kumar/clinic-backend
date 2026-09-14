<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Tests\PlatformTestCase;

class AdminContactMessageTest extends PlatformTestCase
{
    public function test_staff_can_list_and_delete_contact_messages(): void
    {
        ContactMessage::create([
            'full_name' => 'Visitor One',
            'email' => 'one@example.com',
            'phone' => '111',
            'subject' => 'Hours',
            'message' => 'Are you open on Sunday?',
        ]);
        $second = ContactMessage::create([
            'full_name' => 'Visitor Two',
            'email' => 'two@example.com',
            'subject' => 'Price',
            'message' => 'How much is laser?',
        ]);

        $this->actingAsUser($this->createUser(['role' => 'admin']));

        $this->getJson('/api/v1/admin/contact-messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.fullName', 'Visitor Two');

        $this->getJson('/api/v1/admin/contact-messages?search=Sunday')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'one@example.com');

        $this->deleteJson('/api/v1/admin/contact-messages/'.$second->id)
            ->assertOk();

        $this->getJson('/api/v1/admin/contact-messages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_patient_cannot_list_contact_messages(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'patient']));

        $this->getJson('/api/v1/admin/contact-messages')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use Tests\PlatformTestCase;

class ClinicCatalogApiTest extends PlatformTestCase
{
    public function test_lists_clinics_and_clinic_catalog(): void
    {
        $reading = $this->createClinic(['name' => 'Reading', 'slug' => 'reading-api', 'code' => 'LCUK_READ_API']);
        $manchester = $this->createClinic(['name' => 'Manchester', 'slug' => 'manchester-api', 'code' => 'LCUK_MAN_API']);
        $service = $this->createService(['slug' => 'hydrafacial-api']);

        $this->attachServiceToClinic($reading, $service);
        $this->attachServiceToClinic($manchester, $service, [
            'buy_enabled' => false,
            'online_buy_enabled' => false,
        ]);

        $this->getJson('/api/v1/clinics')->assertOk()->assertJsonCount(2, 'data');

        $readingCatalog = $this->getJson("/api/v1/clinics/{$reading->id}/services?mode=buy");
        $readingCatalog->assertOk();
        $this->assertCount(1, $readingCatalog->json('data'));

        $manchesterCatalog = $this->getJson("/api/v1/clinics/{$manchester->id}/services?mode=buy");
        $manchesterCatalog->assertOk();
        $this->assertCount(0, $manchesterCatalog->json('data'));
    }

    public function test_global_services_filter_by_category(): void
    {
        $this->createService(['category' => 'skin', 'slug' => 'skin-one']);
        $this->createService(['category' => 'lhr', 'slug' => 'lhr-one']);

        $this->getJson('/api/v1/services?category=skin')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_contact_form(): void
    {
        $this->postJson('/api/v1/contact', [
            'fullName' => 'Visitor',
            'email' => 'visitor@example.com',
            'phone' => '123',
            'subject' => 'Hello',
            'message' => 'Please call me',
        ])->assertCreated();
    }
}

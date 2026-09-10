<?php

namespace Tests\Feature;

use App\Models\ServiceBenefit;
use App\Models\ServiceFaq;
use Tests\PlatformTestCase;

class ServicePublicApiTest extends PlatformTestCase
{
    public function test_public_services_include_benefits_faqs_and_featured_filter(): void
    {
        $featured = $this->createService([
            'name' => 'Featured Laser',
            'is_featured' => true,
        ]);
        $this->createService([
            'name' => 'Hidden Hydrafacial',
            'is_featured' => false,
        ]);

        ServiceBenefit::create([
            'service_id' => $featured->id,
            'title' => 'Long-lasting results',
            'description' => 'Fewer sessions over time.',
        ]);
        ServiceFaq::create([
            'service_id' => $featured->id,
            'question' => 'How many sessions?',
            'answer' => 'Usually six to ten.',
        ]);

        $this->getJson('/api/v1/services/'.$featured->id)
            ->assertOk()
            ->assertJsonPath('data.benefits.0.title', 'Long-lasting results')
            ->assertJsonPath('data.faqs.0.question', 'How many sessions?')
            ->assertJsonPath('data.isFeatured', true);

        $this->getJson('/api/v1/services?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Featured Laser');
    }

    public function test_superadmin_can_save_benefits_and_faqs(): void
    {
        $this->createClinic(['slug' => 'svc-faq', 'code' => 'SVC_FAQ']);
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/services', [
            'name' => 'FAQ Treatment',
            'appointmentAmount' => 45,
            'isFeatured' => true,
            'benefits' => [
                ['title' => 'Gentle', 'description' => 'Suitable for sensitive skin.'],
                ['title' => '', 'description' => 'skip'],
            ],
            'faqs' => [
                ['question' => 'Does it hurt?', 'answer' => 'Most people feel a light snap.'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.isFeatured', true)
            ->assertJsonCount(1, 'data.benefits')
            ->assertJsonPath('data.faqs.0.question', 'Does it hurt?')
            ->assertJsonPath('data.appointmentAmount', 45)
            ->assertJsonPath('data.appointmentAmountPence', 4500);
    }

    public function test_service_show_includes_same_category_recommendations(): void
    {
        $laser = $this->createService([
            'name' => 'Full Body Laser',
            'category' => 'lhr',
            'treatment_type' => 'laser',
        ]);
        $faceLaser = $this->createService([
            'name' => 'Face Laser',
            'category' => 'lhr',
            'treatment_type' => 'laser',
        ]);
        $this->createService([
            'name' => 'Hydrafacial',
            'category' => 'skin',
            'treatment_type' => 'facial',
        ]);

        $recommended = $this->getJson('/api/v1/services/'.$laser->id)
            ->assertOk()
            ->json('data.recommended');

        $this->assertSame('Face Laser', $recommended[0]['name']);
        $this->assertTrue(collect($recommended)->contains(fn ($row) => (int) $row['id'] === $faceLaser->id));
        $this->assertFalse(collect($recommended)->contains(fn ($row) => (int) $row['id'] === $laser->id));
    }
}

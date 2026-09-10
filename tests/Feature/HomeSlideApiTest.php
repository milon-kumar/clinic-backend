<?php

namespace Tests\Feature;

use App\Models\HomeSlide;
use Tests\PlatformTestCase;

class HomeSlideApiTest extends PlatformTestCase
{
    public function test_public_slides_are_seeded_and_only_active(): void
    {
        $this->getJson('/api/v1/slides')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.image', '/Assets/05.webp')
            ->assertJsonPath('data.0.title', '50% OFF')
            ->assertJsonPath('data.0.offerNote', 'when you buy 3 or more')
            ->assertJsonPath('data.0.bookUrl', '/all-treatment?mode=book')
            ->assertJsonPath('data.0.buyUrl', '/all-treatment?mode=buy');

        HomeSlide::query()->first()?->update(['is_active' => false]);

        $this->getJson('/api/v1/slides')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_creates_and_updates_a_slide(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $created = $this->postJson('/api/v1/admin/slides', [
            'image' => '/storage/slides/hero.jpg',
            'title' => 'Summer glow',
            'subtitle' => 'Book a consultation',
            'offerNote' => 'when you buy 3 or more',
            'bookUrl' => '/all-treatment?mode=book',
            'buyUrl' => '/all-treatment?mode=buy',
            'linkUrl' => '/all-treatment',
            'intervalMs' => 4000,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Summer glow')
            ->assertJsonPath('data.offerNote', 'when you buy 3 or more')
            ->assertJsonPath('data.bookUrl', '/all-treatment?mode=book')
            ->assertJsonPath('data.buyUrl', '/all-treatment?mode=buy');

        $id = $created->json('data.id');

        $this->patchJson('/api/v1/admin/slides/'.$id, [
            'title' => 'Autumn glow',
            'isActive' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Autumn glow')
            ->assertJsonPath('data.isActive', false);
    }

    public function test_branch_staff_cannot_create_slides(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'receptionist']));

        $this->postJson('/api/v1/admin/slides', [
            'image' => '/Assets/05.webp',
        ])->assertForbidden();
    }
}

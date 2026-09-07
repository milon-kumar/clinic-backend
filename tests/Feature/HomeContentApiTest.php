<?php

namespace Tests\Feature;

use App\Models\CategoryLanding;
use App\Models\HomeBlock;
use Tests\PlatformTestCase;

class HomeContentApiTest extends PlatformTestCase
{
    public function test_public_home_blocks_are_seeded(): void
    {
        $this->getJson('/api/v1/home-blocks')
            ->assertOk()
            ->assertJsonPath('data.why.kicker', 'Why Elixir')
            ->assertJsonCount(4, 'data.why.items')
            ->assertJsonCount(4, 'data.concerns.items')
            ->assertJsonCount(3, 'data.promos');
    }

    public function test_public_category_landing_is_seeded(): void
    {
        $this->getJson('/api/v1/landings/botox')
            ->assertOk()
            ->assertJsonPath('data.title', 'Botox')
            ->assertJsonPath('data.category', 'Botox');
    }

    public function test_superadmin_can_update_home_and_landing_copy(): void
    {
        HomeBlock::seedDefaults();
        CategoryLanding::seedDefaults();
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $why = HomeBlock::query()->where('slot', 'why')->first();
        $this->patchJson('/api/v1/admin/home-blocks/'.$why->id, [
            'kicker' => 'Why us',
            'title' => 'Updated why title',
        ])->assertOk()
            ->assertJsonPath('data.kicker', 'Why us');

        $botox = CategoryLanding::query()->where('slug', 'botox')->first();
        $this->patchJson('/api/v1/admin/landings/'.$botox->id, [
            'title' => 'Botox treatments',
            'benefits' => [
                ['icon' => 'bi-stars', 'title' => 'Natural look', 'description' => 'Never overdone.'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.title', 'Botox treatments')
            ->assertJsonCount(1, 'data.benefits');
    }

    public function test_superadmin_can_create_and_delete_category_landing(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/landings', [
            'title' => 'CoolSculpting',
            'slug' => 'coolsculpting',
            'category' => 'body',
            'description' => 'Fat freezing treatments.',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'coolsculpting')
            ->assertJsonPath('data.category', 'body')
            ->assertJsonPath('data.title', 'CoolSculpting');

        $this->getJson('/api/v1/landings')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'coolsculpting']);

        $this->getJson('/api/v1/landings/coolsculpting')
            ->assertOk()
            ->assertJsonPath('data.title', 'CoolSculpting');

        $id = CategoryLanding::query()->where('slug', 'coolsculpting')->value('id');
        $this->deleteJson('/api/v1/admin/landings/'.$id)
            ->assertOk();

        $this->getJson('/api/v1/landings/coolsculpting')->assertNotFound();
    }

    public function test_category_landing_slug_cannot_be_reserved(): void
    {
        $this->actingAsUser($this->createUser(['role' => 'superadmin']));

        $this->postJson('/api/v1/admin/landings', [
            'title' => 'Login',
            'slug' => 'login',
        ])->assertUnprocessable();
    }

    public function test_branch_staff_cannot_edit_home_blocks(): void
    {
        HomeBlock::seedDefaults();
        $why = HomeBlock::query()->where('slot', 'why')->first();
        $this->actingAsUser($this->createUser(['role' => 'receptionist']));

        $this->patchJson('/api/v1/admin/home-blocks/'.$why->id, [
            'title' => 'Nope',
        ])->assertForbidden();
    }
}

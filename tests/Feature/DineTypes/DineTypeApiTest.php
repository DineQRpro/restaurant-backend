<?php

namespace Tests\Feature\DineTypes;

use App\Models\DineType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class DineTypeApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_list_dine_types(): void
    {
        $this->getJson('/api/v1/dine-types')->assertUnauthorized();
    }

    public function test_waiter_can_list_active_dine_types(): void
    {
        DineType::factory()->dineIn()->create();
        DineType::factory()->takeAway()->create();
        DineType::factory()->inactive()->create(['name' => 'Hidden', 'slug' => 'hidden']);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/dine-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['slug' => 'hidden']);
    }
}

<?php

namespace Tests\Feature\Customer\Store;

use App\Enums\ResponseCode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create();
        $this->user->setRole('CUSTOMER');

        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_unauthenticated_user_cannot_update_store()
    {
        $this->putJson(route('customer.store.update'), [
            'name' => 'Hacked Name',
        ])->assertStatus(401);
    }

    public function test_customer_can_update_own_store()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(route('customer.store.update'), [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', 'Updated Name');

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_customer_can_update_store_whatsapp_number()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(route('customer.store.update'), [
            'whatsapp_number' => '+2348099999999',
        ]);

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.whatsapp_number', '+2348099999999');
    }

    public function test_update_with_invalid_data_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->putJson(route('customer.store.update'), [
            'name' => str_repeat('a', 256),
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

<?php

namespace Tests\Feature\Customer\Store;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create();
        $this->user->setRole('CUSTOMER');
    }

    public function test_unauthenticated_user_cannot_create_store()
    {
        $this->postJson(route('customer.store.store'), [
            'name' => 'My Store',
            'whatsapp_number' => '+2348012345678',
        ])->assertStatus(401);
    }

    public function test_store_creation_with_missing_name_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.store.store'), [
            'whatsapp_number' => '+2348012345678',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_store_creation_with_missing_whatsapp_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.store.store'), [
            'name' => 'No WhatsApp Store',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_customer_can_create_store()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.store.store'), [
            'name' => 'My Test Store',
            'whatsapp_number' => '+2348012345678',
        ]);

        $response->assertStatus(ResponseCode::CREATED->value)
            ->assertJson([
                'code' => ResponseCode::CREATED->value,
                'message' => 'Store created successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['store' => ['id', 'name', 'status', 'whatsapp_number']],
            ]);

        $this->assertDatabaseHas('stores', [
            'name' => 'My Test Store',
            'whatsapp_number' => '+2348012345678',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_customer_cannot_create_second_store()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.store.store'), [
            'name' => 'First Store',
            'whatsapp_number' => '+2348012345678',
        ])->assertStatus(ResponseCode::CREATED->value);

        $this->postJson(route('customer.store.store'), [
            'name' => 'Second Store',
            'whatsapp_number' => '+2348098765432',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value)
            ->assertJson(['message' => 'You already have a store. Only one store per account is allowed.']);
    }

    public function test_store_defaults_to_active_status()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.store.store'), [
            'name' => 'Active Store',
            'whatsapp_number' => '+2348012345678',
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals(StoreStatusEnum::ACTIVE->name, $response->json('data.store.status'));
    }

    public function test_customer_can_create_store_with_all_optional_fields()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.store.store'), [
            'name' => 'Full Store',
            'description' => 'A store with all fields',
            'tagline' => 'Best store ever',
            'currency' => 'USD',
            'phone' => '+2348012345678',
            'email' => 'store@example.com',
            'address' => '123 Test Street',
            'whatsapp_number' => '+2348012345678',
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals('Full Store', $response->json('data.store.name'));
        $this->assertEquals('A store with all fields', $response->json('data.store.description'));
        $this->assertEquals('Best store ever', $response->json('data.store.tagline'));
    }
}

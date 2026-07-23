<?php

namespace Tests\Feature\Customer\Category;

use App\Enums\ResponseCode;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateTest extends TestCase
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
        ]);
    }

    public function test_unauthenticated_user_cannot_create_category()
    {
        $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Electronics',
        ])->assertStatus(401);
    }

    public function test_category_creation_without_name_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.stores.categories.store', $this->store->slug), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_customer_can_create_category()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Electronics',
        ]);

        $response->assertStatus(ResponseCode::CREATED->value)
            ->assertJsonStructure([
                'data' => ['category' => ['id', 'name', 'slug', 'store_id']],
            ]);

        $this->assertDatabaseHas('categories', [
            'store_id' => $this->store->id,
            'name' => 'Electronics',
        ]);
    }

    public function test_category_slug_is_unique_within_store()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Electronics',
        ]);

        $response = $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Electronics',
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertNotEquals('electronics', $response->json('data.category.slug'));
    }

    public function test_category_slug_can_duplicate_across_different_stores()
    {
        Sanctum::actingAs($this->user);

        $otherStore = Store::factory()->create(['user_id' => $this->user->id]);

        $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Electronics',
        ])->assertStatus(ResponseCode::CREATED->value);

        $this->postJson(route('customer.stores.categories.store', $otherStore->slug), [
            'name' => 'Electronics',
        ])->assertStatus(ResponseCode::CREATED->value);
    }

    public function test_customer_can_create_subcategory()
    {
        Sanctum::actingAs($this->user);

        $parent = Category::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->postJson(route('customer.stores.categories.store', $this->store->slug), [
            'name' => 'Mobile Phones',
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals($parent->id, $response->json('data.category.parent_id'));
    }

    public function test_customer_cannot_create_category_in_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->postJson(route('customer.stores.categories.store', $otherStore->slug), [
            'name' => 'Hacked Category',
        ])->assertStatus(ResponseCode::FORBIDDEN->value);
    }
}

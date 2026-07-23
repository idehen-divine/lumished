<?php

namespace Tests\Feature\Customer\Store;

use App\Enums\ResponseCode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteTest extends TestCase
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

    public function test_unauthenticated_user_cannot_delete_store()
    {
        $this->deleteJson(route('customer.stores.destroy', $this->store->id))
            ->assertStatus(401);
    }

    public function test_customer_cannot_delete_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->deleteJson(route('customer.stores.destroy', $otherStore->id))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_customer_can_delete_own_store()
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.stores.destroy', $this->store->id))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Store deleted successfully.']);

        $this->assertDatabaseMissing('stores', ['id' => $this->store->id]);
    }

    public function test_store_deletion_cascades_to_products_and_categories()
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $product->categories()->attach($category->id);

        $this->deleteJson(route('customer.stores.destroy', $this->store->id));

        $this->assertDatabaseMissing('stores', ['id' => $this->store->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_product', ['product_id' => $product->id]);
    }
}

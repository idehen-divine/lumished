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
        $this->deleteJson(route('customer.store.destroy'))
            ->assertStatus(401);
    }

    public function test_customer_can_delete_own_store()
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.store.destroy'))
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

        $this->deleteJson(route('customer.store.destroy'));

        $this->assertDatabaseMissing('stores', ['id' => $this->store->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_product', ['product_id' => $product->id]);
    }
}

<?php

namespace Tests\Feature\Customer\Product;

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

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create();
        $this->user->setRole('CUSTOMER');

        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
        ]);
        $this->product->categories()->attach($this->category->id);
    }

    public function test_unauthenticated_user_cannot_delete_product()
    {
        $this->deleteJson(route('customer.products.destroy', $this->product->id))
            ->assertStatus(401);
    }

    public function test_customer_can_delete_product()
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.products.destroy', $this->product->id))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
    }

    public function test_customer_can_delete_all_products_in_own_store()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(3)->create(['store_id' => $this->store->id]);

        $response = $this->deleteJson(route('customer.products.deleteAll'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('message', 'Store products deleted successfully.');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_customer_without_store_cannot_delete_all_products()
    {
        Sanctum::actingAs($this->user);

        $this->user->store()->delete();

        $this->deleteJson(route('customer.products.deleteAll'))
            ->assertStatus(ResponseCode::NOT_FOUND->value)
            ->assertJsonPath('message', 'You do not have a store yet.');
    }
}

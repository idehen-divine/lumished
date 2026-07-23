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

class UpdateTest extends TestCase
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
            'name' => 'Old Name',
            'price' => 1000.00,
        ]);
        $this->product->categories()->attach($this->category->id);
    }

    public function test_unauthenticated_user_cannot_update_product()
    {
        $this->putJson(route('customer.stores.products.update', [$this->store->slug, $this->product->id]), [
            'name' => 'New Name',
        ])->assertStatus(401);
    }

    public function test_customer_cannot_update_product_in_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);
        $otherProduct = Product::factory()->create(['store_id' => $otherStore->id]);

        $this->putJson(route('customer.stores.products.update', [$otherStore->slug, $otherProduct->id]), [
            'name' => 'Hacked',
        ])->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_customer_can_update_product()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(route('customer.stores.products.update', [$this->store->slug, $this->product->id]), [
            'name' => 'New Name',
            'price' => 2000.00,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.product.name', 'New Name')
            ->assertJsonPath('data.product.price', '2000.00');
    }
}

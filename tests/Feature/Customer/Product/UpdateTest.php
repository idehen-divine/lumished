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
            'price' => 100000,
        ]);
        $this->product->categories()->attach($this->category->id);
    }

    public function test_unauthenticated_user_cannot_update_product()
    {
        $this->putJson(route('customer.products.update', $this->product->id), [
            'name' => 'New Name',
        ])->assertStatus(401);
    }

    public function test_customer_can_update_product()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(route('customer.products.update', $this->product->id), [
            'name' => 'New Name',
            'price' => 2000.00,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.product.name', 'New Name')
            ->assertJsonPath('data.product.price', 2000);
    }

    public function test_price_above_max_on_update_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->putJson(route('customer.products.update', $this->product->id), [
            'name' => 'New Name',
            'price' => 99999999999999999999,
            'category_ids' => [$this->category->id],
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

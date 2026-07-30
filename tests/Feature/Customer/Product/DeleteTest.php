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
}

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

class GetTest extends TestCase
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
            'name' => 'Viewable Product',
        ]);
        $this->product->categories()->attach($this->category->id);
    }

    public function test_unauthenticated_user_cannot_list_products()
    {
        $this->getJson(route('customer.stores.products.index', $this->store->slug))
            ->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_product()
    {
        $this->getJson(route('customer.stores.products.show', [$this->store->slug, $this->product->id]))
            ->assertStatus(401);
    }

    public function test_customer_can_list_products_for_own_store()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(3)->create(['store_id' => $this->store->id])
            ->each(fn ($p) => $p->categories()->attach($this->category->id));

        $response = $this->getJson(route('customer.stores.products.index', $this->store->slug));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['products', 'pagination'],
            ]);
    }

    public function test_customer_can_view_product()
    {
        Sanctum::actingAs($this->user);

        $this->getJson(route('customer.stores.products.show', [$this->store->slug, $this->product->id]))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.product.name', 'Viewable Product');
    }

    public function test_customer_cannot_list_products_for_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->getJson(route('customer.stores.products.index', $otherStore->slug))
            ->assertStatus(ResponseCode::FORBIDDEN->value);
    }
}

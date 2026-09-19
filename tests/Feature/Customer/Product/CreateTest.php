<?php

namespace Tests\Feature\Customer\Product;

use App\Enums\ProductStatusEnum;
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

    private Category $category;

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
    }

    public function test_unauthenticated_user_cannot_create_product()
    {
        $this->postJson(route('customer.products.store'), [
            'name' => 'Test Product',
            'price' => 2500.00,
            'category_ids' => [$this->category->id],
        ])->assertStatus(401);
    }

    public function test_product_creation_without_name_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.products.store'), [
            'price' => 1000.00,
            'category_ids' => [$this->category->id],
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_product_creation_without_price_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.products.store'), [
            'name' => 'No Price',
            'category_ids' => [$this->category->id],
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_product_creation_without_categories_is_optional()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.products.store'), [
            'name' => 'No Category',
            'price' => 1000.00,
        ])->assertStatus(ResponseCode::CREATED->value);
    }

    public function test_product_price_cannot_be_negative()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.products.store'), [
            'name' => 'Negative Price',
            'price' => -100,
            'category_ids' => [$this->category->id],
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_customer_can_create_product()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Test Product',
            'price' => 2500.00,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value)
            ->assertJsonStructure([
                'data' => ['product' => ['id', 'name', 'price', 'status', 'categories']],
            ]);

        $this->assertDatabaseHas('products', [
            'store_id' => $this->store->id,
            'name' => 'Test Product',
            'price' => 250000,
        ]);
    }

    public function test_product_defaults_to_draft_status()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Draft Product',
            'price' => 1000.00,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals(ProductStatusEnum::DRAFT->name, $response->json('data.product.status'));
    }

    public function test_product_pricing_allows_zero()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Free Product',
            'price' => 0,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals(0, $response->json('data.product.price'));
    }

    public function test_customer_can_create_product_with_all_optional_fields()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Full Product',
            'description' => 'A detailed product description',
            'price' => 5000.00,
            'compare_at_price' => 6500.00,
            'stock_quantity' => 10,
            'status' => ProductStatusEnum::PUBLISHED->name,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertEquals('Full Product', $response->json('data.product.name'));
        $this->assertEquals(ProductStatusEnum::PUBLISHED->name, $response->json('data.product.status'));
    }

    public function test_price_above_max_on_create_returns_validation_error()
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.products.store'), [
            'name' => 'Overflow Product',
            'price' => 99999999999999999999,
            'category_ids' => [$this->category->id],
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_high_price_within_column_limit_is_saved()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Expensive Product',
            'price' => 12213213213,
            'category_ids' => [$this->category->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);
        $this->assertDatabaseHas('products', [
            'name' => 'Expensive Product',
            'price' => 1221321321300,
        ]);
    }

    public function test_product_can_have_multiple_categories()
    {
        Sanctum::actingAs($this->user);

        $category2 = Category::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->postJson(route('customer.products.store'), [
            'name' => 'Multi Category Product',
            'price' => 3000.00,
            'category_ids' => [$this->category->id, $category2->id],
        ]);

        $response->assertStatus(ResponseCode::CREATED->value);

        $productId = $response->json('data.product.id');
        $this->assertDatabaseHas('category_product', [
            'product_id' => $productId,
            'category_id' => $this->category->id,
        ]);
        $this->assertDatabaseHas('category_product', [
            'product_id' => $productId,
            'category_id' => $category2->id,
        ]);
    }
}

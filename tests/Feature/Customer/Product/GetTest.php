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
        $this->getJson(route('customer.products.index'))
            ->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_product()
    {
        $this->getJson(route('customer.products.show', $this->product->id))
            ->assertStatus(401);
    }

    public function test_customer_can_list_products_for_own_store()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(3)->create(['store_id' => $this->store->id])
            ->each(fn ($p) => $p->categories()->attach($this->category->id));

        $response = $this->getJson(route('customer.products.index'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['products', 'pagination'],
            ]);
    }

    public function test_customer_can_view_product()
    {
        Sanctum::actingAs($this->user);

        $this->getJson(route('customer.products.show', $this->product->id))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.product.name', 'Viewable Product');
    }

    public function test_customer_can_search_products_by_name()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'name' => 'Unrelated Gadget',
        ]);

        $response = $this->getJson(route('customer.products.index', ['search' => 'Viewable']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $names = collect($response->json('data.products'))->pluck('name');
        $this->assertContains('Viewable Product', $names);
        $this->assertNotContains('Unrelated Gadget', $names);
    }

    public function test_customer_can_filter_products_by_active_status()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'PUBLISHED',
        ]);

        $response = $this->getJson(route('customer.products.index', ['status' => 'active']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $statuses = collect($response->json('data.products'))->pluck('status');
        $this->assertNotEmpty($statuses);
        $this->assertTrue($statuses->every(fn ($status) => $status === 'PUBLISHED'));
    }

    public function test_customer_can_filter_products_by_inactive_status()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'PUBLISHED',
        ]);

        $response = $this->getJson(route('customer.products.index', ['status' => 'inactive']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $statuses = collect($response->json('data.products'))->pluck('status');
        $this->assertNotEmpty($statuses);
        $this->assertTrue($statuses->every(fn ($status) => $status === 'DRAFT'));
    }

    public function test_customer_can_sort_products()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Aardvark']);
        Product::factory()->create(['store_id' => $this->store->id, 'name' => 'Zebra']);

        $response = $this->getJson(route('customer.products.index', ['sort_by' => 'name', 'sort_order' => 'desc']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $names = collect($response->json('data.products'))->pluck('name')->values()->all();
        $this->assertEquals(['Zebra', 'Viewable Product', 'Aardvark'], $names);
    }

    public function test_customer_can_request_custom_per_page()
    {
        Sanctum::actingAs($this->user);

        Product::factory()->count(5)->create(['store_id' => $this->store->id]);

        $response = $this->getJson(route('customer.products.index', ['per_page' => 2]));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 6);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_customer_per_page_out_of_range_is_rejected()
    {
        Sanctum::actingAs($this->user);

        $this->getJson(route('customer.products.index', ['per_page' => 0]))
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);

        $this->getJson(route('customer.products.index', ['per_page' => 101]))
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

<?php

namespace Tests\Feature\Public;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreGetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_can_list_active_stores()
    {
        $user = User::factory()->create();
        Store::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $response = $this->getJson(route('public.stores.index'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['stores', 'pagination'],
            ]);
        $this->assertCount(3, $response->json('data.stores'));
    }

    public function test_public_cannot_see_inactive_or_suspended_stores()
    {
        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::ACTIVE->name]);
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::INACTIVE->name]);
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::SUSPENDED->name]);

        $response = $this->getJson(route('public.stores.index'));

        $this->assertCount(1, $response->json('data.stores'));
    }

    public function test_public_can_view_active_store_by_slug()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Store',
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $this->getJson(route('public.stores.show', $store->slug))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', 'Public Store');
    }

    public function test_public_cannot_view_inactive_store_by_slug()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Inactive Store',
            'status' => StoreStatusEnum::INACTIVE->name,
        ]);

        $this->getJson(route('public.stores.show', $store->slug))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_cannot_view_suspended_store_by_slug()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Suspended Store',
            'status' => StoreStatusEnum::SUSPENDED->name,
        ]);

        $this->getJson(route('public.stores.show', $store->slug))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_can_list_published_products_for_active_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $category = Category::factory()->create(['store_id' => $store->id]);

        $publishedProducts = Product::factory()->count(2)->create([
            'store_id' => $store->id,
            'status' => 'PUBLISHED',
        ]);
        foreach ($publishedProducts as $product) {
            $product->categories()->attach($category->id);
        }

        $draftProduct = Product::factory()->create([
            'store_id' => $store->id,
            'status' => 'DRAFT',
        ]);
        $draftProduct->categories()->attach($category->id);

        $response = $this->getJson(route('public.stores.products', $store->slug));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_public_cannot_see_products_for_inactive_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::INACTIVE->name,
        ]);

        $this->getJson(route('public.stores.products', $store->slug))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_can_view_published_product_by_id()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'name' => 'Public Product',
            'status' => 'PUBLISHED',
        ]);

        $this->getJson(route('public.products.show', $product->id))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.product.name', 'Public Product');
    }

    public function test_public_cannot_view_draft_product()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'name' => 'Draft Product',
            'status' => 'DRAFT',
        ]);

        $this->getJson(route('public.products.show', $product->id))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_can_view_categories_for_active_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        Category::factory()->count(2)->create(['store_id' => $store->id]);

        $this->getJson(route('public.stores.categories', $store->slug))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure(['data' => ['categories']]);
    }

    public function test_nonexistent_store_returns_404()
    {
        $this->getJson(route('public.stores.show', 'nonexistent-slug'))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_store_list_includes_products_count()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        Product::factory()->count(2)->create([
            'store_id' => $store->id,
            'status' => 'PUBLISHED',
        ]);

        $response = $this->getJson(route('public.stores.index'));

        $this->assertArrayHasKey('products_count', $response->json('data.stores.0'));
    }

    public function test_public_store_list_is_paginated()
    {
        $user = User::factory()->create();
        Store::factory()->count(20)->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);

        $response = $this->getJson(route('public.stores.index'));

        $response->assertJsonStructure([
            'data' => ['pagination' => ['current_page', 'last_page', 'per_page', 'total']],
        ]);
    }
}

<?php

namespace Tests\Feature\Public;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreGetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_can_view_active_store_by_slug()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Store',
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
        ]);

        $this->getJson(route('public.store.show', ['slug' => Str::slug($store->name)]))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', 'Public Store');
    }

    public function test_public_cannot_view_inactive_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Inactive Store',
            'status' => StoreStatusEnum::INACTIVE->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
        ]);

        $this->getJson(route('public.store.show', ['slug' => Str::slug($store->name)]))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_cannot_view_suspended_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Suspended Store',
            'status' => StoreStatusEnum::SUSPENDED->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
        ]);

        $this->getJson(route('public.store.show', ['slug' => Str::slug($store->name)]))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_can_list_published_products_for_active_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
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

        $response = $this->getJson(route('public.store.products', ['slug' => Str::slug($store->name)]));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $this->assertCount(2, $response->json('data.products'));
    }

    public function test_public_cannot_see_products_for_inactive_store()
    {
        $user = User::factory()->create();
        Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::INACTIVE->name,
        ]);

        $this->getJson(route('public.store.products', ['slug' => 'some-slug']))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_public_can_search_published_products()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
        ]);

        $category = Category::factory()->create(['store_id' => $store->id]);

        $matching = Product::factory()->create([
            'store_id' => $store->id,
            'status' => 'PUBLISHED',
            'name' => 'Signature Sneakers',
        ]);
        $matching->categories()->attach($category->id);

        $other = Product::factory()->create([
            'store_id' => $store->id,
            'status' => 'PUBLISHED',
            'name' => 'Plain T-Shirt',
        ]);
        $other->categories()->attach($category->id);

        $response = $this->getJson(route('public.store.products', ['slug' => Str::slug($store->name), 'search' => 'Sneakers']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $names = collect($response->json('data.products'))->pluck('name');
        $this->assertContains('Signature Sneakers', $names);
        $this->assertNotContains('Plain T-Shirt', $names);
    }

    public function test_public_can_view_categories_for_active_store()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);
        StoreSettings::factory()->create([
            'store_id' => $store->id,
            'slug' => Str::slug($store->name),
        ]);

        Category::factory()->count(2)->create(['store_id' => $store->id]);

        $response = $this->getJson(route('public.store.categories', ['slug' => Str::slug($store->name)]));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure(['data' => ['categories']]);
    }

    public function test_nonexistent_store_returns_404()
    {
        $this->getJson(route('public.store.show', ['slug' => 'nonexistent-slug']))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_store_show_requires_slug_or_domain()
    {
        $this->getJson(route('public.store.show'))
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_store_products_requires_slug_or_domain()
    {
        $this->getJson(route('public.store.products'))
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

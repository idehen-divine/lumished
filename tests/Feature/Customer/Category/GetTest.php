<?php

namespace Tests\Feature\Customer\Category;

use App\Enums\ResponseCode;
use App\Models\Category;
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
            'name' => 'Books',
        ]);
    }

    public function test_unauthenticated_user_cannot_list_categories()
    {
        $this->getJson(route('customer.stores.categories.index', $this->store->slug))
            ->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_category()
    {
        $this->getJson(route('customer.stores.categories.show', [$this->store->slug, $this->category->id]))
            ->assertStatus(401);
    }

    public function test_customer_can_list_categories_for_own_store()
    {
        Sanctum::actingAs($this->user);

        Category::factory()->count(3)->create(['store_id' => $this->store->id]);

        $response = $this->getJson(route('customer.stores.categories.index', $this->store->slug));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['categories'],
            ]);
        $this->assertCount(4, $response->json('data.categories'));
    }

    public function test_customer_can_view_category()
    {
        Sanctum::actingAs($this->user);

        $this->getJson(route('customer.stores.categories.show', [$this->store->slug, $this->category->id]))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.category.name', 'Books');
    }

    public function test_customer_cannot_view_category_in_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);
        $otherCategory = Category::factory()->create(['store_id' => $otherStore->id]);

        $this->getJson(route('customer.stores.categories.show', [$otherStore->slug, $otherCategory->id]))
            ->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_category_list_returns_tree_structure()
    {
        Sanctum::actingAs($this->user);

        $parent = Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Parent',
        ]);

        Category::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $response = $this->getJson(route('customer.stores.categories.index', $this->store->slug));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $categories = $response->json('data.categories');
        $this->assertCount(2, $categories);
    }
}

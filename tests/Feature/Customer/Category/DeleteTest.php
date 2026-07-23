<?php

namespace Tests\Feature\Customer\Category;

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

    public function test_unauthenticated_user_cannot_delete_category()
    {
        $this->deleteJson(route('customer.stores.categories.destroy', [$this->store->slug, $this->category->id]))
            ->assertStatus(401);
    }

    public function test_customer_cannot_delete_category_in_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);
        $otherCategory = Category::factory()->create(['store_id' => $otherStore->id]);

        $this->deleteJson(route('customer.stores.categories.destroy', [$otherStore->slug, $otherCategory->id]))
            ->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_customer_can_delete_category()
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.stores.categories.destroy', [$this->store->slug, $this->category->id]))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertDatabaseMissing('categories', ['id' => $this->category->id]);
    }

    public function test_category_deletion_reparents_children()
    {
        Sanctum::actingAs($this->user);

        $child = Category::factory()->create([
            'store_id' => $this->store->id,
            'parent_id' => $this->category->id,
        ]);

        $this->deleteJson(route('customer.stores.categories.destroy', [$this->store->slug, $this->category->id]));

        $child->refresh();
        $this->assertNull($child->parent_id);
    }

    public function test_category_deletion_unassigns_products()
    {
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $product->categories()->attach($this->category->id);

        $this->deleteJson(route('customer.stores.categories.destroy', [$this->store->slug, $this->category->id]));

        $this->assertDatabaseMissing('category_product', [
            'category_id' => $this->category->id,
            'product_id' => $product->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Admin\Store;

use App\Enums\ResponseCode;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->setRole('ADMIN');
    }

    public function test_unauthenticated_user_cannot_delete_store_products()
    {
        $store = Store::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->deleteJson(route('admin.stores.products.delete', $store->id))
            ->assertStatus(401);
    }

    public function test_non_admin_cannot_delete_store_products()
    {
        $store = Store::factory()->create(['user_id' => User::factory()->create()->id]);

        $customer = User::factory()->create();
        $customer->setRole('CUSTOMER');

        Sanctum::actingAs($customer);

        $this->deleteJson(route('admin.stores.products.delete', $store->id))
            ->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_admin_can_delete_all_products_in_a_store()
    {
        Sanctum::actingAs($this->admin);

        $store = Store::factory()->create(['user_id' => User::factory()->create()->id]);
        Product::factory()->count(3)->create(['store_id' => $store->id]);

        $response = $this->deleteJson(route('admin.stores.products.delete', $store->id));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('message', 'Store products deleted successfully.');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_delete_products_of_one_store_without_affecting_others()
    {
        Sanctum::actingAs($this->admin);

        $store = Store::factory()->create(['user_id' => User::factory()->create()->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->create()->id]);

        Product::factory()->count(2)->create(['store_id' => $store->id]);
        Product::factory()->count(4)->create(['store_id' => $otherStore->id]);

        $this->deleteJson(route('admin.stores.products.delete', $store->id))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertDatabaseCount('products', 4);
        $this->assertDatabaseMissing('products', ['store_id' => $store->id]);
    }
}

<?php

namespace Tests\Feature\Admin\Store;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTest extends TestCase
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

    public function test_unauthenticated_user_cannot_list_stores()
    {
        $this->getJson(route('admin.stores.index'))->assertStatus(401);
    }

    public function test_unverified_admin_is_blocked_from_protected_routes()
    {
        $unverified = User::factory()->unverified()->create();
        $unverified->setRole('ADMIN');

        Sanctum::actingAs($unverified);

        $this->getJson(route('admin.stores.index'))
            ->assertStatus(ResponseCode::PRECONDITION_REQUIRED->value)
            ->assertJson(['message' => 'Your email address is not verified.']);
    }

    public function test_non_admin_cannot_list_stores()
    {
        Sanctum::actingAs($this->admin);

        $customer = User::factory()->create();
        $customer->setRole('CUSTOMER');

        Sanctum::actingAs($customer);

        $this->getJson(route('admin.stores.index'))
            ->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_admin_can_list_all_stores()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        Store::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->getJson(route('admin.stores.index'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['stores', 'pagination'],
            ]);
        $this->assertCount(3, $response->json('data.stores'));
    }

    public function test_admin_list_includes_all_statuses()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::ACTIVE->name]);
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::INACTIVE->name]);
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::SUSPENDED->name]);

        $response = $this->getJson(route('admin.stores.index'));

        $this->assertCount(3, $response->json('data.stores'));
    }

    public function test_admin_can_view_any_store()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
            'name' => 'Admin Visible Store',
        ]);

        $this->getJson(route('admin.stores.show', $store->id))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', 'Admin Visible Store');
    }

    public function test_admin_can_view_products_in_any_store()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        Product::factory()->count(2)->create(['store_id' => $store->id]);

        $response = $this->getJson(route('admin.stores.products', $store->id));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['products', 'pagination'],
            ]);
    }

    public function test_admin_can_search_stores()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id, 'name' => 'Lagos Emporium']);
        Store::factory()->create(['user_id' => $user->id, 'name' => 'Abuja Boutique']);

        $response = $this->getJson(route('admin.stores.index', ['search' => 'Emporium']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $names = collect($response->json('data.stores'))->pluck('name');
        $this->assertContains('Lagos Emporium', $names);
        $this->assertNotContains('Abuja Boutique', $names);
    }

    public function test_admin_can_filter_stores_by_status()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::ACTIVE->name]);
        Store::factory()->create(['user_id' => $user->id, 'status' => StoreStatusEnum::INACTIVE->name]);

        $response = $this->getJson(route('admin.stores.index', ['status' => 'active']));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $statuses = collect($response->json('data.stores'))->pluck('status');
        $this->assertNotEmpty($statuses);
        $this->assertTrue($statuses->every(fn ($status) => $status === StoreStatusEnum::ACTIVE->name));
    }
}

<?php

namespace Tests\Feature\Admin\Store;

use App\Enums\ResponseCode;
use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->setRole('ADMIN');

        $user = User::factory()->create();
        $this->store = Store::factory()->create([
            'user_id' => $user->id,
            'status' => StoreStatusEnum::ACTIVE->name,
        ]);
    }

    public function test_unauthenticated_user_cannot_update_store_status()
    {
        $this->putJson(route('admin.stores.status.update', $this->store->id), [
            'status' => StoreStatusEnum::SUSPENDED->name,
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_update_store_status()
    {
        $customer = User::factory()->create();
        $customer->setRole('CUSTOMER');

        Sanctum::actingAs($customer);

        $this->putJson(route('admin.stores.status.update', $this->store->id), [
            'status' => StoreStatusEnum::SUSPENDED->name,
        ])->assertStatus(ResponseCode::FORBIDDEN->value);
    }

    public function test_admin_can_suspend_a_store()
    {
        Sanctum::actingAs($this->admin);

        $this->putJson(route('admin.stores.status.update', $this->store->id), [
            'status' => StoreStatusEnum::SUSPENDED->name,
        ])
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.status', StoreStatusEnum::SUSPENDED->name);

        $this->assertDatabaseHas('stores', [
            'id' => $this->store->id,
            'status' => StoreStatusEnum::SUSPENDED->name,
        ]);
    }

    public function test_admin_can_activate_a_suspended_store()
    {
        Sanctum::actingAs($this->admin);

        $this->store->update(['status' => StoreStatusEnum::SUSPENDED->name]);

        $this->putJson(route('admin.stores.status.update', $this->store->id), [
            'status' => StoreStatusEnum::ACTIVE->name,
        ])
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.status', StoreStatusEnum::ACTIVE->name);
    }

    public function test_admin_status_update_with_invalid_status_returns_validation_error()
    {
        Sanctum::actingAs($this->admin);

        $this->putJson(route('admin.stores.status.update', $this->store->id), [
            'status' => 'invalid_status',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

<?php

namespace Tests\Feature\Customer\Store;

use App\Enums\ResponseCode;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create();
        $this->user->setRole('CUSTOMER');

        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_view_store()
    {
        $this->getJson(route('customer.store.show'))->assertStatus(401);
    }

    public function test_customer_can_view_own_store()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('customer.store.show'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', $this->store->name);
    }

    public function test_returns_store_not_found_when_no_store_exists()
    {
        $userWithoutStore = User::factory()->create();
        $userWithoutStore->setRole('CUSTOMER');
        Sanctum::actingAs($userWithoutStore);

        $this->getJson(route('customer.store.show'))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }
}

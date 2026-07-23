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

    public function test_unauthenticated_user_cannot_list_stores()
    {
        $this->getJson(route('customer.stores.index'))->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_store()
    {
        $this->getJson(route('customer.stores.show', $this->store->id))->assertStatus(401);
    }

    public function test_customer_can_list_own_stores()
    {
        Sanctum::actingAs($this->user);

        Store::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('customer.stores.index'));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'data' => ['stores', 'pagination'],
            ]);
        $this->assertCount(4, $response->json('data.stores'));
    }

    public function test_customer_cannot_see_other_users_stores()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        Store::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson(route('customer.stores.index'));

        $response->assertStatus(ResponseCode::SUCCESS->value);
        $this->assertCount(1, $response->json('data.stores'));
    }

    public function test_customer_can_view_own_store()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('customer.stores.show', $this->store->id));

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.store.name', $this->store->name);
    }

    public function test_customer_cannot_view_another_users_store()
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherUser->id]);

        $this->getJson(route('customer.stores.show', $otherStore->id))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }

    public function test_returns_404_for_nonexistent_store()
    {
        Sanctum::actingAs($this->user);

        $this->getJson(route('customer.stores.show', '00000000-0000-0000-0000-000000000000'))
            ->assertStatus(ResponseCode::NOT_FOUND->value);
    }
}

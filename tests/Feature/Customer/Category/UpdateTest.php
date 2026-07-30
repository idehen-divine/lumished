<?php

namespace Tests\Feature\Customer\Category;

use App\Enums\ResponseCode;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateTest extends TestCase
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
            'name' => 'Old Name',
        ]);
    }

    public function test_unauthenticated_user_cannot_update_category()
    {
        $this->putJson(route('customer.categories.update', $this->category->id), [
            'name' => 'New Name',
        ])->assertStatus(401);
    }

    public function test_customer_can_update_category_name()
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson(route('customer.categories.update', $this->category->id), [
            'name' => 'New Name',
        ]);

        $response->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonPath('data.category.name', 'New Name');
    }

    public function test_category_update_prevents_self_parent()
    {
        Sanctum::actingAs($this->user);

        $this->putJson(route('customer.categories.update', $this->category->id), [
            'parent_id' => $this->category->id,
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

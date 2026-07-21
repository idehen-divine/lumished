<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create([
            'email' => $this->faker->unique()->safeEmail(),
        ]);
        $this->user->setRole('CUSTOMER');
        $this->user->markEmailAsVerified();
        $this->token = $this->user->createToken('auth')->plainTextToken;
    }

    /**********************    Account Deletion    ***********************/

    public function test_delete_account_without_auth_returns_unauthorized(): void
    {
        $this->deleteJson(route('customer.account.security.account.delete'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_authenticated_user_can_delete_account(): void
    {
        $this->withToken($this->token)
            ->deleteJson(route('customer.account.security.account.delete'), [
                'password' => 'password',
            ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Your account has been deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    /**********************    Data Export    ***********************/

    public function test_export_data_without_auth_returns_unauthorized(): void
    {
        $this->getJson(route('customer.account.security.account.export'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_authenticated_user_can_export_data(): void
    {
        $this->withToken($this->token)
            ->getJson(route('customer.account.security.account.export'))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['user', 'data'],
            ]);
    }
}

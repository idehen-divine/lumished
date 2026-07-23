<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LogoutTest extends TestCase
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

    public function test_logout_without_auth_returns_unauthorized(): void
    {
        $this->deleteJson(route('customer.auth.logout'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->withToken($this->token)
            ->deleteJson(route('customer.auth.logout'))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $this->withToken($this->token)
            ->deleteJson(route('customer.auth.logout'))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }

    public function test_revoked_token_cannot_be_used_again(): void
    {
        $this->withToken($this->token)
            ->deleteJson(route('customer.auth.logout'))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }
}

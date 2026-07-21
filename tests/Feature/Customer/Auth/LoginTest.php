<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create([
            'email' => $this->faker->unique()->safeEmail(),
        ]);
        $this->user->setRole('CUSTOMER');
    }

    public function test_login_with_missing_email_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'password' => 'password',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_login_with_missing_password_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_login_with_invalid_email_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_login_with_wrong_password_returns_unauthorized(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'WrongPass1',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['token'],
            ]);
    }

    public function test_login_revokes_previous_tokens(): void
    {
        $this->user->createToken('auth');

        $this->assertCount(1, $this->user->tokens);

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertCount(1, $this->user->fresh()->tokens);
    }

    public function test_login_with_valid_credentials_returns_user_data(): void
    {
        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => [
                    'user' => [
                        'id', 'first_name', 'last_name', 'email', 'role',
                    ],
                    'token',
                    'roles',
                    'permissions',
                ],
            ]);
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.auth.login'), [
                'email' => $this->user->email,
                'password' => 'WrongPass1',
            ]);
        }

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertStatus(423);
    }

    public function test_locked_account_returns_lockout_error_even_with_correct_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.auth.login'), [
                'email' => $this->user->email,
                'password' => 'WrongPass1',
            ]);
        }

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertStatus(423)
            ->assertJson(['message' => 'Account is temporarily locked. Try again later.']);
    }
}

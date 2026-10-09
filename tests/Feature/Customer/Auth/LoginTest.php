<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\ResponseCode;
use App\Models\User;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;
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

    public function test_social_login_with_missing_provider_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.social.login'), [
            'access_token' => 'some-token',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_social_login_with_invalid_provider_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.social.login'), [
            'provider' => 'TWITTER',
            'access_token' => 'some-token',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_social_login_with_missing_access_token_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.social.login'), [
            'provider' => 'GOOGLE',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_social_login_with_invalid_token_returns_server_error(): void
    {
        $this->postJson(route('customer.auth.social.login'), [
            'provider' => 'GOOGLE',
            'access_token' => 'invalid-token',
        ])->assertStatus(ResponseCode::SERVER_ERROR->value);
    }

    public function test_social_login_without_configured_credentials_returns_clear_error(): void
    {
        config()->set('services.firebase.credentials', '');

        $this->postJson(route('customer.auth.social.login'), [
            'provider' => 'GOOGLE',
            'access_token' => 'some-token',
        ])->assertStatus(ResponseCode::SERVER_ERROR->value)
            ->assertJson(['message' => 'Social login is not configured. Please try again later.']);
    }

    public function test_social_login_with_valid_payload_returns_token(): void
    {
        $this->app->singleton(SocialAuthService::class, fn () => new class extends ServiceApi implements SocialAuthService
        {
            /**
             * Fake social login returning a successful response without Firebase.
             *
             * @param  mixed  $request  Request containing provider and access_token
             * @param  string  $role  Role to assign if creating a new user
             * @return SocialAuthService Returns service response with fake token
             */
            public function socialLogin($request, string $role): SocialAuthService
            {
                return $this->setCode(ResponseCode::SUCCESS->value)
                    ->setMessage('Authentication successful')
                    ->setData([
                        'token' => 'fake-token',
                        'roles' => [$role],
                    ]);
            }
        });

        $this->postJson(route('customer.auth.social.login'), [
            'provider' => 'GOOGLE',
            'access_token' => 'valid-token',
        ])
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['token'],
            ]);
    }
}

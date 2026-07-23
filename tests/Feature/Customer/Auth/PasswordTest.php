<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\OTPTypeEnum;
use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordTest extends TestCase
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

    public function test_initiate_password_change_without_auth_returns_unauthorized(): void
    {
        $this->patchJson(route('customer.account.security.password.update'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_initiate_password_change_returns_pending_token(): void
    {
        $this->withToken($this->token)
            ->patchJson(route('customer.account.security.password.update'))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['pending_token'],
            ]);
    }

    public function test_initiate_password_change_creates_otp_in_cache(): void
    {
        $this->withToken($this->token)
            ->patchJson(route('customer.account.security.password.update'))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertNotNull(
            Cache::get(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email)
        );
    }

    public function test_verify_password_change_otp_with_missing_fields_returns_validation_error(): void
    {
        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.verify'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_verify_password_change_otp_with_valid_data_returns_reset_token(): void
    {
        $pendingToken = Str::random(64);
        Cache::put('password_update_pending:'.hash('sha256', $pendingToken), $this->user->id, now()->addMinutes(5));
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.verify'), [
                'otp' => '111111',
                'pending_token' => $pendingToken,
            ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['reset_token'],
            ]);
    }

    public function test_verify_password_change_otp_with_invalid_otp_returns_error(): void
    {
        $pendingToken = Str::random(64);
        Cache::put('password_update_pending:'.hash('sha256', $pendingToken), $this->user->id, now()->addMinutes(5));
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.verify'), [
                'otp' => '000000',
                'pending_token' => $pendingToken,
            ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_verify_password_change_otp_with_invalid_token_returns_error(): void
    {
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.verify'), [
                'otp' => '111111',
                'pending_token' => 'invalid-token',
            ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_confirm_password_update_with_missing_reset_token_returns_validation_error(): void
    {
        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.confirm'), [
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_confirm_password_update_with_valid_token_updates_password(): void
    {
        $resetToken = Str::random(64);
        Cache::put('password_update:'.hash('sha256', $resetToken), $this->user->id, now()->addMinutes(5));

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.confirm'), [
                'reset_token' => $resetToken,
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Password updated successfully.']);
    }

    public function test_confirm_password_update_with_invalid_token_returns_error(): void
    {
        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.confirm'), [
                'reset_token' => 'invalid-token',
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_full_password_change_flow_succeeds(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson(route('customer.account.security.password.update'));
        $response->assertStatus(ResponseCode::SUCCESS->value);
        $pendingToken = $response->json('data.pending_token');

        $response = $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.verify'), [
                'otp' => '111111',
                'pending_token' => $pendingToken,
            ]);
        $response->assertStatus(ResponseCode::SUCCESS->value);
        $resetToken = $response->json('data.reset_token');

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.password.update.confirm'), [
                'reset_token' => $resetToken,
                'password' => 'NewPass456',
                'password_confirmation' => 'NewPass456',
            ])->assertStatus(ResponseCode::SUCCESS->value);

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'NewPass456',
        ])->assertStatus(ResponseCode::SUCCESS->value);
    }
}

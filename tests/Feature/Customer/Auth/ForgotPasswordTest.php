<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\OTPTypeEnum;
use App\Enums\ResponseCode;
use App\Mail\ResetPasswordOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
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

    public function test_forgot_password_with_missing_email_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.forgot'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_forgot_password_with_invalid_email_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.forgot'), [
            'email' => 'not-an-email',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_forgot_password_with_non_existent_email_returns_success_without_revealing(): void
    {
        $this->postJson(route('customer.auth.password.forgot'), [
            'email' => 'nonexistent@example.com',
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson([
                'message' => 'If this email is registered, a reset code has been sent.',
            ])
            ->assertJsonPath('data.pending_token', fn ($v) => is_string($v) && strlen($v) > 0);
    }

    public function test_forgot_password_with_valid_email_sends_otp_and_returns_pending_token(): void
    {
        Mail::fake();

        $this->postJson(route('customer.auth.password.forgot'), [
            'email' => $this->user->email,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['pending_token'],
            ]);

        Mail::assertQueued(ResetPasswordOtpMail::class);
        $this->assertEquals('111111', Cache::get(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email));
    }

    public function test_verify_password_otp_with_missing_fields_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.verify-otp'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_verify_password_otp_with_missing_otp_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.verify-otp'), [
            'pending_token' => 'some-token',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_verify_password_otp_with_valid_otp_and_token_returns_reset_token(): void
    {
        $pendingToken = Str::random(64);
        Cache::put('password_reset_pending:'.hash('sha256', $pendingToken), $this->user->id, now()->addMinutes(5));
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->postJson(route('customer.auth.password.verify-otp'), [
            'otp' => '111111',
            'pending_token' => $pendingToken,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'message',
                'data' => ['reset_token'],
            ]);
    }

    public function test_verify_password_otp_with_invalid_otp_returns_error(): void
    {
        $pendingToken = Str::random(64);
        Cache::put('password_reset_pending:'.hash('sha256', $pendingToken), $this->user->id, now()->addMinutes(5));
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->postJson(route('customer.auth.password.verify-otp'), [
            'otp' => '000000',
            'pending_token' => $pendingToken,
        ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_verify_password_otp_with_invalid_pending_token_returns_error(): void
    {
        Cache::put(OTPTypeEnum::RESET_PASSWORD_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->postJson(route('customer.auth.password.verify-otp'), [
            'otp' => '111111',
            'pending_token' => 'invalid-token',
        ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_reset_password_with_missing_reset_token_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.reset'), [
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_reset_password_with_missing_password_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.reset'), [
            'reset_token' => 'some-token',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_reset_password_with_password_mismatch_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.password.reset'), [
            'reset_token' => 'some-token',
            'password' => 'NewPass123',
            'password_confirmation' => 'Different1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_reset_password_with_valid_token_resets_password(): void
    {
        $resetToken = Str::random(64);
        Cache::put('password_reset:'.hash('sha256', $resetToken), $this->user->id, now()->addMinutes(5));

        $this->postJson(route('customer.auth.password.reset'), [
            'reset_token' => $resetToken,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(ResponseCode::SUCCESS->value);
    }

    public function test_reset_password_with_invalid_token_returns_error(): void
    {
        $this->postJson(route('customer.auth.password.reset'), [
            'reset_token' => 'invalid-token',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(ResponseCode::BAD_REQUEST->value);
    }

    public function test_full_password_reset_flow_succeeds(): void
    {
        $newPassword = 'NewPass123';

        $response = $this->postJson(route('customer.auth.password.forgot'), [
            'email' => $this->user->email,
        ]);
        $response->assertStatus(ResponseCode::SUCCESS->value);
        $pendingToken = $response->json('data.pending_token');

        $response = $this->postJson(route('customer.auth.password.verify-otp'), [
            'otp' => '111111',
            'pending_token' => $pendingToken,
        ]);
        $response->assertStatus(ResponseCode::SUCCESS->value);
        $resetToken = $response->json('data.reset_token');

        $this->postJson(route('customer.auth.password.reset'), [
            'reset_token' => $resetToken,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertStatus(ResponseCode::SUCCESS->value);

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => $newPassword,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['token'],
            ]);
    }
}

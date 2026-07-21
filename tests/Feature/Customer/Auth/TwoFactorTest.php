<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
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
        $this->user->markEmailAsVerified();
    }

    private function totpFromSecret(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function setupAndConfirmTwoFactor(): string
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson(route('customer.account.security.2fa.setup'));
        $secret = $response->json('data.secret');

        $code = $this->totpFromSecret($secret);
        $this->postJson(route('customer.account.security.2fa.confirm'), [
            'code' => $code,
        ]);

        $this->user->refresh();

        return $secret;
    }

    /**********************    Setup 2FA    ***********************/

    public function test_setup_two_factor_without_auth_returns_unauthorized(): void
    {
        $this->postJson(route('customer.account.security.2fa.setup'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_authenticated_user_can_setup_two_factor(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.account.security.2fa.setup'))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['qr_code_url', 'secret'],
            ]);
    }

    /**********************    Confirm 2FA    ***********************/

    public function test_confirm_two_factor_without_auth_returns_unauthorized(): void
    {
        $this->postJson(route('customer.account.security.2fa.confirm'), [
            'code' => '123456',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_confirm_two_factor_without_pending_setup_returns_error(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.account.security.2fa.confirm'), [
            'code' => '123456',
        ])->assertStatus(ResponseCode::BAD_REQUEST->value)
            ->assertJson(['message' => 'No pending 2FA setup found. Please start setup again.']);
    }

    public function test_authenticated_user_can_confirm_two_factor(): void
    {
        Sanctum::actingAs($this->user);
        $setupResponse = $this->postJson(route('customer.account.security.2fa.setup'));
        $secret = $setupResponse->json('data.secret');

        $code = $this->totpFromSecret($secret);
        $this->postJson(route('customer.account.security.2fa.confirm'), [
            'code' => $code,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Two-factor authentication enabled successfully.']);

        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
    }

    /**********************    Disable 2FA    ***********************/

    public function test_disable_two_factor_without_auth_returns_unauthorized(): void
    {
        $this->deleteJson(route('customer.account.security.2fa.disable'), [
            'code' => '123456',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_disable_two_factor_when_not_enabled_returns_error(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.account.security.2fa.disable'), [
            'code' => '123456',
        ])->assertStatus(ResponseCode::BAD_REQUEST->value)
            ->assertJson(['message' => 'Two-factor authentication is not enabled.']);
    }

    public function test_authenticated_user_can_disable_two_factor(): void
    {
        $secret = $this->setupAndConfirmTwoFactor();

        $code = $this->totpFromSecret($secret);
        $this->deleteJson(route('customer.account.security.2fa.disable'), [
            'code' => $code,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Two-factor authentication disabled successfully.']);

        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
    }

    /**********************    Verify 2FA (Login-time)    ***********************/

    public function test_verify_two_factor_with_invalid_challenge_token_returns_error(): void
    {
        $this->postJson(route('customer.auth.2fa.verify'), [
            'challenge_token' => '00000000-0000-0000-0000-000000000000',
            'method' => 'totp',
            'code' => '123456',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value)
            ->assertJson(['message' => 'Invalid or expired challenge token.']);
    }

    public function test_verify_two_factor_with_valid_challenge_and_totp_returns_token(): void
    {
        $secret = $this->setupAndConfirmTwoFactor();

        $loginResponse = $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ]);
        $loginResponse->assertStatus(ResponseCode::SUCCESS->value);
        $challengeToken = $loginResponse->json('data.challenge_token');
        $this->assertNotNull($challengeToken);

        $code = $this->totpFromSecret($secret);
        $this->postJson(route('customer.auth.2fa.verify'), [
            'challenge_token' => $challengeToken,
            'method' => 'totp',
            'code' => $code,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJsonStructure([
                'code',
                'data' => ['token'],
            ]);
    }

    /**********************    Resend 2FA Email    ***********************/

    public function test_resend_two_factor_email_with_invalid_challenge_token_returns_error(): void
    {
        $this->postJson(route('customer.auth.2fa.email'), [
            'challenge_token' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value)
            ->assertJson(['message' => 'Invalid or expired challenge token.']);
    }

    public function test_resend_two_factor_email_with_valid_challenge_returns_success(): void
    {
        $secret = $this->setupAndConfirmTwoFactor();

        $loginResponse = $this->postJson(route('customer.auth.login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ]);
        $challengeToken = $loginResponse->json('data.challenge_token');
        $this->assertNotNull($challengeToken);

        $this->postJson(route('customer.auth.2fa.email'), [
            'challenge_token' => $challengeToken,
        ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'A verification code has been sent to your email.']);
    }

    /**********************    Missing Fields Validation    ***********************/

    public function test_verify_two_factor_with_missing_fields_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.2fa.verify'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_confirm_two_factor_with_missing_code_returns_validation_error(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson(route('customer.account.security.2fa.confirm'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_disable_two_factor_with_missing_code_returns_validation_error(): void
    {
        Sanctum::actingAs($this->user);

        $this->deleteJson(route('customer.account.security.2fa.disable'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_resend_two_factor_email_with_missing_challenge_token_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.2fa.email'), [])
            ->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

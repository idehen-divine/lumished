<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\OTPTypeEnum;
use App\Enums\ResponseCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->unverified()->create([
            'email' => $this->faker->unique()->safeEmail(),
        ]);
        $this->user->setRole('CUSTOMER');
        $this->token = $this->user->createToken('auth')->plainTextToken;
    }

    public function test_verify_email_without_auth_returns_unauthorized(): void
    {
        $this->postJson(route('customer.account.security.email.verify'), [
            'otp' => '111111',
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_unverified_user_can_verify_email_with_valid_otp(): void
    {
        Cache::put(OTPTypeEnum::VERIFY_EMAIL_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->assertFalse($this->user->hasVerifiedEmail());

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.verify'), ['otp' => '111111'])
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Email verified successfully.']);

        $this->assertTrue($this->user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_email_with_invalid_otp_returns_error(): void
    {
        Cache::put(OTPTypeEnum::VERIFY_EMAIL_OTP->name.'_'.$this->user->email, '111111', now()->addMinutes(5));

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.verify'), ['otp' => '000000'])
            ->assertStatus(ResponseCode::BAD_REQUEST->value)
            ->assertJson(['message' => 'Invalid or expired OTP code.']);
    }

    public function test_verified_user_receives_already_verified_response(): void
    {
        $this->user->markEmailAsVerified();

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.verify'), ['otp' => '111111'])
            ->assertStatus(ResponseCode::ALREADY_REPORTED->value);
    }

    public function test_resend_verification_without_auth_returns_unauthorized(): void
    {
        $this->postJson(route('customer.account.security.email.resend'))
            ->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_resend_verification_returns_success(): void
    {
        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.resend'))
            ->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Verification code resent. Please check your email.']);
    }

    public function test_resend_verification_stores_new_otp(): void
    {
        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.resend'))
            ->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertNotNull(Cache::get(OTPTypeEnum::VERIFY_EMAIL_OTP->name.'_'.$this->user->email));
    }

    public function test_verified_user_receives_already_verified_on_resend(): void
    {
        $this->user->markEmailAsVerified();

        $this->withToken($this->token)
            ->postJson(route('customer.account.security.email.resend'))
            ->assertStatus(ResponseCode::ALREADY_REPORTED->value);
    }

    public function test_update_email_without_auth_returns_unauthorized(): void
    {
        $this->patchJson(route('customer.account.security.email.update'), [
            'email' => $this->faker->unique()->safeEmail(),
        ])->assertStatus(ResponseCode::UNAUTHORIZED->value);
    }

    public function test_update_email_returns_success_and_sends_otp(): void
    {
        $newEmail = $this->faker->unique()->safeEmail();

        $this->withToken($this->token)
            ->patchJson(route('customer.account.security.email.update'), [
                'email' => $newEmail,
            ])->assertStatus(ResponseCode::SUCCESS->value)
            ->assertJson(['message' => 'Verification OTP sent. Please check your inbox.']);

        $this->assertEquals($newEmail, $this->user->fresh()->email);
    }

    public function test_update_email_marks_new_email_unverified(): void
    {
        $this->user->markEmailAsVerified();
        $newEmail = $this->faker->unique()->safeEmail();

        $this->withToken($this->token)
            ->patchJson(route('customer.account.security.email.update'), [
                'email' => $newEmail,
            ])->assertStatus(ResponseCode::SUCCESS->value);

        $this->assertFalse($this->user->fresh()->hasVerifiedEmail());
    }

    public function test_update_email_with_duplicate_email_returns_validation_error(): void
    {
        $existingUser = User::factory()->create([
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $this->withToken($this->token)
            ->patchJson(route('customer.account.security.email.update'), [
                'email' => $existingUser->email,
            ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }
}

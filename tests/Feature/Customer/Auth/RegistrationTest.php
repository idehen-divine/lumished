<?php

namespace Tests\Feature\Customer\Auth;

use App\Enums\OTPTypeEnum;
use App\Enums\ResponseCode;
use App\Mail\VerifyEmailOtpMail;
use App\Mail\WelcomeCustomerMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_registration_with_missing_first_name_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_missing_last_name_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_missing_email_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_missing_password_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_password_too_short_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_password_missing_uppercase_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_password_missing_digit_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password',
            'password_confirmation' => 'Password',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_password_confirmation_mismatch_returns_validation_error(): void
    {
        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password1',
            'password_confirmation' => 'Different1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_duplicate_email_returns_validation_error(): void
    {
        $existingEmail = $this->faker->unique()->safeEmail();
        User::factory()->create(['email' => $existingEmail]);

        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $existingEmail,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::VALIDATION_ERROR->value);
    }

    public function test_registration_with_valid_data_returns_created_and_sends_mails(): void
    {
        Mail::fake();

        $email = $this->faker->unique()->safeEmail();

        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])
            ->assertStatus(ResponseCode::CREATED->value)
            ->assertJsonStructure([
                'code',
                'data' => ['token'],
            ]);

        Mail::assertQueued(WelcomeCustomerMail::class);
        Mail::assertQueued(VerifyEmailOtpMail::class);
        $this->assertNotNull(Cache::get(OTPTypeEnum::VERIFY_EMAIL_OTP->name.'_'.$email));
    }

    public function test_registration_creates_user_with_customer_role(): void
    {
        $email = $this->faker->unique()->safeEmail();

        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => $email,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::CREATED->value);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals('CUSTOMER', $user->getRole());
    }

    public function test_registered_user_starts_unverified(): void
    {
        $email = $this->faker->unique()->safeEmail();

        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(ResponseCode::CREATED->value);

        $user = User::where('email', $email)->first();
        $this->assertFalse($user->hasVerifiedEmail());
    }
}

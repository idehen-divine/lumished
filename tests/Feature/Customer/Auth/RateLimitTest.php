<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
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

    public function test_login_endpoint_is_rate_limited_to_five_per_minute(): void
    {
        RateLimiter::for('auth', fn () => Limit::perMinute(5));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.auth.login'), [
                'email' => $this->faker->unique()->safeEmail(),
                'password' => 'WrongPass1',
            ]);
        }

        $this->postJson(route('customer.auth.login'), [
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'WrongPass1',
        ])->assertStatus(429);
    }

    public function test_forgot_password_endpoint_is_rate_limited(): void
    {
        RateLimiter::for('auth', fn () => Limit::perMinute(5));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.auth.password.forgot'), [
                'email' => $this->faker->unique()->safeEmail(),
            ]);
        }

        $this->postJson(route('customer.auth.password.forgot'), [
            'email' => $this->faker->unique()->safeEmail(),
        ])->assertStatus(429);
    }

    public function test_register_endpoint_is_rate_limited(): void
    {
        RateLimiter::for('auth', fn () => Limit::perMinute(5));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.auth.register'), [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => $this->faker->unique()->safeEmail(),
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
            ]);
        }

        $this->postJson(route('customer.auth.register'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ])->assertStatus(429);
    }
}

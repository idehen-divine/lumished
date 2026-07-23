<?php

namespace Database\Factories;

use App\Enums\StoreStatusEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'tagline' => fake()->catchPhrase(),
            'currency' => 'NGN',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'whatsapp_number' => fake()->phoneNumber(),
            'status' => StoreStatusEnum::ACTIVE->name,
        ];
    }
}

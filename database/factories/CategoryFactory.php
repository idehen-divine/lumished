<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
        ];
    }
}

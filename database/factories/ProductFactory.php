<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10000, 10000000),
            'compare_at_price' => fake()->optional()->numberBetween(100000, 20000000),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'status' => 'DRAFT',
        ];
    }
}

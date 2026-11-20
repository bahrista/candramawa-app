<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandProduct> */
class BrandProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'type' => 'product',
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'links' => [],
        ];
    }
}

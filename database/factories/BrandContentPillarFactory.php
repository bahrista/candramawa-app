<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandContentPillar;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandContentPillar> */
class BrandContentPillarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}

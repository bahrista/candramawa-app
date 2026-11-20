<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandKnowledgeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BrandKnowledgeItem> */
class BrandKnowledgeItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'title' => fake()->sentence(3),
            'category' => 'Tone of voice',
            'content' => fake()->paragraph(),
            'links' => [],
        ];
    }
}

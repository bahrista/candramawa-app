<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Brand;
use App\Models\ContentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentPlan> */
class ContentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'title' => fake()->sentence(4),
            'publish_at' => now()->addDays(5),
            'deadline_mode' => ContentPlan::DEADLINE_AUTO,
            'deadline_at' => now()->addDays(4),
            'content_manager_id' => User::factory(),
            'brief' => fake()->paragraph(),
            'brief_references' => [],
            'status' => ContentStatus::Draft,
        ];
    }

    public function status(ContentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function inProduction(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::InProduction, 'production_started_at' => now()]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::InReview, 'production_started_at' => now(), 'review_submitted_at' => now()]);
    }

    /** @param  list<string>  $types  content type keys, see config/content_types.php */
    public function withPosts(array $types = ['instagram_single']): static
    {
        return $this->afterCreating(function (ContentPlan $plan) use ($types) {
            foreach ($types as $type) {
                $plan->posts()->create(['content_type' => $type, 'media_links' => [], 'tags' => []]);
            }
        });
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BrandRole;
use App\Models\Brand;
use App\Models\BrandContentPillar;
use App\Models\BrandMember;
use App\Models\ContentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandPillarTest extends TestCase
{
    use RefreshDatabase;

    private function member(Brand $brand, BrandRole $role): User
    {
        $user = User::factory()->create();
        BrandMember::create(['brand_id' => $brand->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_content_managers_and_owners_manage_pillars_but_other_roles_only_look(): void
    {
        $brand = Brand::factory()->create();

        foreach ([BrandRole::ContentManager, BrandRole::BrandOwner] as $role) {
            $this->actingAs($this->member($brand, $role))
                ->post("/brands/{$brand->id}/pillars", ['name' => "Pillar of {$role->value}", 'description' => '  Why we exist '])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('Why we exist', BrandContentPillar::where('name', 'Pillar of brand_owner')->value('description'));

        foreach ([BrandRole::Publisher, BrandRole::Copywriter, BrandRole::MultimediaCreator] as $role) {
            $this->actingAs($this->member($brand, $role))->post("/brands/{$brand->id}/pillars", ['name' => 'Nope'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->post("/brands/{$brand->id}/pillars", ['name' => 'Nope'])->assertForbidden();

        $this->assertSame(2, $brand->pillars()->count());
    }

    public function test_names_are_required_and_unique_per_brand_ignoring_case(): void
    {
        $brand = Brand::factory()->create();
        $other = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $this->member($other, BrandRole::ContentManager);
        BrandContentPillar::factory()->create(['brand_id' => $brand->id, 'name' => 'Educational']);

        $this->actingAs($me);
        $this->post("/brands/{$brand->id}/pillars", ['name' => ''])->assertSessionHasErrors('name');
        $this->post("/brands/{$brand->id}/pillars", ['name' => 'educational'])->assertSessionHasErrors('name');
        $this->post("/brands/{$brand->id}/pillars", ['name' => str_repeat('x', 101)])->assertSessionHasErrors('name');

        // another brand may use the same name
        $this->actingAs($other->members()->first()->user)->post("/brands/{$other->id}/pillars", ['name' => 'Educational'])->assertSessionHasNoErrors();
    }

    public function test_rename_archive_restore_and_delete(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $pillar = BrandContentPillar::factory()->create(['brand_id' => $brand->id, 'name' => 'Promo']);
        BrandContentPillar::factory()->create(['brand_id' => $brand->id, 'name' => 'Educational']);

        $this->actingAs($me);
        $this->put("/brands/{$brand->id}/pillars/{$pillar->id}", ['name' => 'Promotional'])->assertSessionHasNoErrors();
        $this->put("/brands/{$brand->id}/pillars/{$pillar->id}", ['name' => 'Promotional'])->assertSessionHasNoErrors(); // may keep its own name
        $this->put("/brands/{$brand->id}/pillars/{$pillar->id}", ['name' => 'EDUCATIONAL'])->assertSessionHasErrors('name');
        $this->assertSame('Promotional', $pillar->fresh()->name);

        $this->patch("/brands/{$brand->id}/pillars/{$pillar->id}/archive")->assertSessionHas('success', 'Pillar archived.');
        $this->assertTrue($pillar->fresh()->isArchived());
        $this->patch("/brands/{$brand->id}/pillars/{$pillar->id}/archive")->assertSessionHas('success', 'Pillar restored.');
        $this->assertFalse($pillar->fresh()->isArchived());

        $this->delete("/brands/{$brand->id}/pillars/{$pillar->id}")->assertSessionHas('success');
        $this->assertModelMissing($pillar);
    }

    public function test_a_pillar_in_use_cannot_be_deleted_only_archived(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $pillar = BrandContentPillar::factory()->create(['brand_id' => $brand->id, 'name' => 'Promo']);
        ContentPlan::factory()->count(2)->create(['brand_id' => $brand->id, 'content_pillar_id' => $pillar->id]);

        $this->actingAs($me)->delete("/brands/{$brand->id}/pillars/{$pillar->id}")
            ->assertSessionHasErrors(['pillar' => '"Promo" is used by 2 content plans. Archive it instead.']);

        $this->assertModelExists($pillar);
        $this->get("/brands/{$brand->id}?tab=pillars")->assertInertia(fn ($page) => $page
            ->where('pillars.0.name', 'Promo')->where('pillars.0.plans', 2)->where('pillars.0.archived', false));
    }

    public function test_a_pillar_of_another_brand_is_not_found(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $foreign = BrandContentPillar::factory()->create();

        $this->actingAs($me)->put("/brands/{$brand->id}/pillars/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->delete("/brands/{$brand->id}/pillars/{$foreign->id}")->assertNotFound();
        $this->assertNotSame('Hijack', $foreign->fresh()->name);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BrandRole;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\ContentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BrandTeamTest extends TestCase
{
    use RefreshDatabase;

    private function brandWith(array $team = []): array
    {
        $creator = User::factory()->create();
        $brand = Brand::factory()->create(['created_by' => $creator->id]);
        $people = [];

        foreach ($team as $name => $role) {
            $people[$name] = User::factory()->create();
            BrandMember::create(['brand_id' => $brand->id, 'user_id' => $people[$name]->id, 'role' => $role]);
        }

        return [$brand, $creator, $people];
    }

    private function roles(Brand $brand, string $role): array
    {
        return $brand->members()->where('role', $role)->pluck('user_id')->sort()->values()->all();
    }

    public function test_the_person_who_creates_a_brand_starts_as_its_brand_owner(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/brands', ['name' => 'Kopi Senja', 'color' => '#aa5500'])
            ->assertRedirect('/brands/'.Brand::firstOrFail()->id.'?tab=team');

        $brand = Brand::firstOrFail();
        $this->assertSame([$me->id], $this->roles($brand, 'brand_owner'));
        $this->assertSame($me->id, $brand->created_by);
    }

    public function test_the_brand_page_shows_the_team_and_what_the_viewer_may_do(): void
    {
        [$brand, $creator, $p] = $this->brandWith([
            'owner' => BrandRole::BrandOwner, 'cm' => BrandRole::ContentManager, 'designer' => BrandRole::MultimediaCreator,
            'writer' => BrandRole::Copywriter, 'publisher' => BrandRole::Publisher,
        ]);

        $cases = [
            [$p['owner'], ['update' => true, 'manageAssets' => true, 'createContent' => true]],
            [$p['cm'], ['update' => false, 'manageAssets' => true, 'createContent' => true]],
            [$p['designer'], ['update' => false, 'manageAssets' => false, 'createContent' => false]],
            [$p['publisher'], ['update' => false, 'manageAssets' => false, 'createContent' => false]],
            [$creator, ['update' => true, 'manageAssets' => true, 'createContent' => true]],
            [User::factory()->superadmin()->create(), ['update' => true, 'manageAssets' => true, 'createContent' => true]],
        ];

        foreach ($cases as [$user, $abilities]) {
            $this->actingAs($user)->get("/brands/{$brand->id}")->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Brands/Show')
                ->where('tab', 'overview')
                ->where('abilities', $abilities)
                ->has('roles', 5)
                ->where('team.content_manager.0.id', $p['cm']->id)
                ->where('team.publisher.0.id', $p['publisher']->id)
                ->where('team.brand_owner', fn ($owners) => count($owners) === 1)
                ->has('users', $abilities['update'] ? User::where('is_active', true)->count() : 0));
        }

        $this->actingAs($p['writer'])->get("/brands/{$brand->id}?tab=pillars")->assertInertia(fn ($page) => $page->where('tab', 'pillars'));
        $this->get("/brands/{$brand->id}?tab=hacker")->assertInertia(fn ($page) => $page->where('tab', 'overview'));
    }

    public function test_only_team_members_creator_and_superadmin_can_open_a_brand(): void
    {
        [$brand] = $this->brandWith(['writer' => BrandRole::Copywriter]);

        $this->actingAs(User::factory()->create())->get("/brands/{$brand->id}")
            ->assertForbidden()->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
    }

    public function test_brands_list_includes_brands_where_the_user_is_on_the_team(): void
    {
        [$brand, , $p] = $this->brandWith(['publisher' => BrandRole::Publisher]);
        Brand::factory()->create(); // unrelated

        $this->actingAs($p['publisher'])->get('/brands')->assertInertia(fn ($page) => $page
            ->has('brands.data', 1)->where('brands.data.0.id', $brand->id)->where('brands.data.0.can_update', false));
    }

    public function test_a_brand_owner_changes_the_team_and_the_roles_are_kept_apart(): void
    {
        [$brand, , $p] = $this->brandWith(['owner' => BrandRole::BrandOwner, 'old' => BrandRole::Publisher]);
        $ani = User::factory()->create();
        $budi = User::factory()->create();

        $this->actingAs($p['owner'])->put("/brands/{$brand->id}/team", ['team' => [
            'brand_owner' => [$p['owner']->id],
            'content_manager' => [$ani->id],
            'multimedia_creator' => [$budi->id, $budi->id, $ani->id], // the same person may hold several roles
            'copywriter' => [],
            'publisher' => [$budi->id],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame([$p['owner']->id], $this->roles($brand, 'brand_owner'));
        $this->assertSame([$ani->id], $this->roles($brand, 'content_manager'));
        $this->assertSame(collect([$ani->id, $budi->id])->sort()->values()->all(), $this->roles($brand, 'multimedia_creator'));
        $this->assertSame([$budi->id], $this->roles($brand, 'publisher')); // "old" was removed
        $this->assertSame([], $this->roles($brand, 'copywriter'));
    }

    public function test_the_payload_is_the_whole_team_so_a_missing_role_is_emptied(): void
    {
        [$brand, $creator, $p] = $this->brandWith(['writer' => BrandRole::Copywriter]);

        $this->actingAs($creator)->put("/brands/{$brand->id}/team", ['team' => ['brand_owner' => [$creator->id]]])->assertSessionHasNoErrors();

        $this->assertSame([], $this->roles($brand, 'copywriter'));
    }

    public function test_only_brand_managers_can_change_the_team(): void
    {
        [$brand, , $p] = $this->brandWith(['cm' => BrandRole::ContentManager, 'designer' => BrandRole::MultimediaCreator]);
        $payload = ['team' => ['brand_owner' => [$p['cm']->id]]];

        foreach ([$p['cm'], $p['designer'], User::factory()->create()] as $user) {
            $this->actingAs($user)->put("/brands/{$brand->id}/team", $payload)->assertForbidden();
        }

        $this->assertSame([], $this->roles($brand, 'brand_owner'));
    }

    public function test_team_members_must_exist_and_new_ones_must_be_active(): void
    {
        [$brand, $creator] = $this->brandWith();
        $inactive = User::factory()->inactive()->create();

        $this->actingAs($creator);
        $this->put("/brands/{$brand->id}/team", ['team' => ['publisher' => [99999]]])->assertSessionHasErrors('team.publisher.0');
        $this->put("/brands/{$brand->id}/team", ['team' => ['publisher' => [$inactive->id]]])->assertSessionHasErrors('team.publisher');
        $this->put("/brands/{$brand->id}/team", [])->assertSessionHasErrors('team');

        // someone already on the team may stay after being deactivated
        BrandMember::create(['brand_id' => $brand->id, 'user_id' => $inactive->id, 'role' => BrandRole::Publisher]);
        $this->put("/brands/{$brand->id}/team", ['team' => ['publisher' => [$inactive->id]]])->assertSessionHasNoErrors();
        $this->assertSame([$inactive->id], $this->roles($brand, 'publisher'));
    }

    public function test_the_creator_keeps_control_even_after_leaving_the_team_list(): void
    {
        [$brand, $creator] = $this->brandWith();

        $this->actingAs($creator)->put("/brands/{$brand->id}/team", ['team' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $this->roles($brand, 'brand_owner'));

        $this->get("/brands/{$brand->id}/edit")->assertOk();
        $this->put("/brands/{$brand->id}/team", ['team' => ['brand_owner' => [$creator->id]]])->assertSessionHasNoErrors();
    }

    public function test_removing_someone_from_the_team_does_not_unassign_them_from_plans(): void
    {
        [$brand, $creator, $p] = $this->brandWith(['writer' => BrandRole::Copywriter]);
        $plan = ContentPlan::factory()->inProduction()->create(['brand_id' => $brand->id, 'content_manager_id' => $creator->id, 'copywriter_id' => $p['writer']->id]);

        $this->actingAs($creator)->put("/brands/{$brand->id}/team", ['team' => ['brand_owner' => [$creator->id]]])->assertSessionHasNoErrors();

        $this->assertSame($p['writer']->id, $plan->fresh()->copywriter_id);
        $this->actingAs($p['writer'])->get("/content-plans/{$plan->id}")->assertOk(); // still assigned, so still visible
    }
}

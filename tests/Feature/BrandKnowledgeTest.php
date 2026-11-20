<?php

namespace Tests\Feature;

use App\Enums\BrandRole;
use App\Models\Brand;
use App\Models\BrandKnowledgeItem;
use App\Models\BrandMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private function member(Brand $brand, BrandRole $role): User
    {
        $user = User::factory()->create();
        BrandMember::create(['brand_id' => $brand->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_a_content_manager_adds_an_entry_with_links(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);

        $this->actingAs($me)->post("/brands/{$brand->id}/knowledge", [
            'title' => 'Tone of voice',
            'category' => 'Guidelines',
            'content' => "Warm.\nNever formal.",
            'links' => [
                ['url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz123456/view', 'label' => ' Brand book '],
                ['url' => '', 'label' => 'blank rows are dropped'],
            ],
        ])->assertSessionHasNoErrors();

        $item = BrandKnowledgeItem::firstOrFail();
        $this->assertSame($me->id, $item->created_by);
        $this->assertSame($brand->id, $item->brand_id);
        $this->assertSame("Warm.\nNever formal.", $item->content);
        $this->assertSame([['url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz123456/view', 'label' => 'Brand book']], $item->links);
    }

    public function test_title_and_content_are_required_and_links_must_be_web_addresses(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::BrandOwner);

        $this->actingAs($me)->post("/brands/{$brand->id}/knowledge", ['title' => '', 'content' => ''])->assertSessionHasErrors(['title', 'content']);
        $this->post("/brands/{$brand->id}/knowledge", ['title' => 'T', 'content' => 'C', 'links' => [['url' => 'javascript:alert(1)']]])
            ->assertSessionHasErrors('links.0.url');
        $this->post("/brands/{$brand->id}/knowledge", ['title' => 'T', 'content' => str_repeat('a', 20001)])->assertSessionHasErrors('content');
        $this->assertSame(0, BrandKnowledgeItem::count());
    }

    public function test_every_team_member_can_open_the_share_link_but_only_managers_edit(): void
    {
        $brand = Brand::factory()->create();
        $item = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id, 'title' => 'Audience']);

        foreach ([BrandRole::Publisher, BrandRole::Copywriter, BrandRole::MultimediaCreator] as $role) {
            $this->actingAs($this->member($brand, $role));
            $this->get("/brands/{$brand->id}/knowledge/{$item->id}")->assertInertia(fn ($page) => $page
                ->component('Brands/Knowledge/Show')->where('item.title', 'Audience')->where('canManage', false)
                ->where('item.share_url', "/brands/{$brand->id}/knowledge/{$item->id}"));
            $this->get("/brands/{$brand->id}/knowledge/{$item->id}/edit")->assertForbidden();
            $this->put("/brands/{$brand->id}/knowledge/{$item->id}", ['title' => 'Hacked', 'content' => 'x'])->assertForbidden();
            $this->delete("/brands/{$brand->id}/knowledge/{$item->id}")->assertForbidden();
        }

        $this->actingAs($this->member($brand, BrandRole::ContentManager))->get("/brands/{$brand->id}/knowledge/{$item->id}")
            ->assertInertia(fn ($page) => $page->where('canManage', true));
        $this->assertSame('Audience', $item->fresh()->title);
    }

    public function test_outsiders_and_other_brands_entries_are_not_reachable(): void
    {
        $brand = Brand::factory()->create();
        $item = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id]);
        $mine = Brand::factory()->create();
        $me = $this->member($mine, BrandRole::ContentManager);

        $this->actingAs($me)->get("/brands/{$brand->id}/knowledge/{$item->id}")->assertForbidden();   // not on that brand's team
        $this->get("/brands/{$mine->id}/knowledge/{$item->id}")->assertNotFound();                    // entry of another brand
        $this->put("/brands/{$mine->id}/knowledge/{$item->id}", ['title' => 'x', 'content' => 'y'])->assertNotFound();
    }

    public function test_update_and_delete(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $item = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($me)->get("/brands/{$brand->id}/knowledge/{$item->id}/edit")->assertInertia(fn ($page) => $page
            ->component('Brands/Knowledge/Form')->where('item.id', $item->id)->has('categories'));

        $this->put("/brands/{$brand->id}/knowledge/{$item->id}", ['title' => 'New title', 'content' => 'New content'])
            ->assertRedirect("/brands/{$brand->id}/knowledge/{$item->id}");
        $this->assertSame('New title', $item->fresh()->title);

        $this->delete("/brands/{$brand->id}/knowledge/{$item->id}")->assertRedirect("/brands/{$brand->id}?tab=knowledge");
        $this->assertModelMissing($item);
    }

    public function test_the_brand_page_lists_entries_with_share_links(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::Publisher);
        $item = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id, 'title' => 'FAQ', 'content' => "A\n\nB   C"]);

        $this->actingAs($me)->get("/brands/{$brand->id}?tab=knowledge")->assertInertia(fn ($page) => $page
            ->where('knowledge.0.title', 'FAQ')->where('knowledge.0.excerpt', 'A B C')
            ->where('knowledge.0.share_url', "/brands/{$brand->id}/knowledge/{$item->id}"));
    }

    public function test_a_guest_opening_a_share_link_logs_in_and_comes_back(): void
    {
        $brand = Brand::factory()->create();
        $item = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id]);

        $this->get("/brands/{$brand->id}/knowledge/{$item->id}")->assertRedirect('/login')
            ->assertSessionHas('url.intended', url("/brands/{$brand->id}/knowledge/{$item->id}"));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BrandRole;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\BrandProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandProductTest extends TestCase
{
    use RefreshDatabase;

    private function member(Brand $brand, BrandRole $role): User
    {
        $user = User::factory()->create();
        BrandMember::create(['brand_id' => $brand->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'type' => 'product', 'name' => 'Signature latte', 'description' => 'Espresso with palm sugar.',
            'key_benefits' => "Local beans\nLess sweet", 'price' => 'Rp 32.000', 'is_active' => true, 'links' => [],
        ], $override);
    }

    public function test_a_content_manager_adds_a_product_and_a_service(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);

        $this->actingAs($me)->post("/brands/{$brand->id}/products", $this->payload())->assertSessionHasNoErrors();
        $this->post("/brands/{$brand->id}/products", $this->payload(['type' => 'service', 'name' => 'Barista class', 'price' => null]))->assertSessionHasNoErrors();

        $this->assertSame(['Signature latte', 'Barista class'], BrandProduct::orderBy('id')->pluck('name')->all());
        $this->assertSame($me->id, BrandProduct::first()->created_by);
        $this->assertSame('service', BrandProduct::orderByDesc('id')->first()->type);
    }

    public function test_validation(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::BrandOwner);

        $this->actingAs($me);
        $this->post("/brands/{$brand->id}/products", $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post("/brands/{$brand->id}/products", $this->payload(['type' => 'gadget']))->assertSessionHasErrors('type');
        $this->post("/brands/{$brand->id}/products", $this->payload(['links' => [['url' => 'ftp://x.test/a']]]))->assertSessionHasErrors('links.0.url');
        $this->post("/brands/{$brand->id}/products", $this->payload(['description' => str_repeat('a', 5001)]))->assertSessionHasErrors('description');
        $this->assertSame(0, BrandProduct::count());
    }

    public function test_every_team_member_can_open_the_share_link_but_only_managers_edit(): void
    {
        $brand = Brand::factory()->create();
        $product = BrandProduct::factory()->create(['brand_id' => $brand->id, 'name' => 'Cold brew']);

        $this->actingAs($this->member($brand, BrandRole::Publisher));
        $this->get("/brands/{$brand->id}/products/{$product->id}")->assertInertia(fn ($page) => $page
            ->component('Brands/Products/Show')->where('item.name', 'Cold brew')->where('canManage', false)
            ->where('item.share_url', "/brands/{$brand->id}/products/{$product->id}"));
        $this->get("/brands/{$brand->id}/products/{$product->id}/edit")->assertForbidden();
        $this->put("/brands/{$brand->id}/products/{$product->id}", $this->payload(['name' => 'Hacked']))->assertForbidden();
        $this->delete("/brands/{$brand->id}/products/{$product->id}")->assertForbidden();

        $this->actingAs(User::factory()->create())->get("/brands/{$brand->id}/products/{$product->id}")->assertForbidden();
    }

    public function test_an_entry_of_another_brand_is_not_found_and_update_delete_work(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::ContentManager);
        $product = BrandProduct::factory()->create(['brand_id' => $brand->id]);
        $foreign = BrandProduct::factory()->create();

        $this->actingAs($me)->get("/brands/{$brand->id}/products/{$foreign->id}")->assertNotFound();

        $this->put("/brands/{$brand->id}/products/{$product->id}", $this->payload(['name' => 'Renamed', 'is_active' => false]))
            ->assertRedirect("/brands/{$brand->id}/products/{$product->id}");
        $this->assertSame('Renamed', $product->fresh()->name);
        $this->assertFalse($product->fresh()->is_active);

        $this->delete("/brands/{$brand->id}/products/{$product->id}")->assertRedirect("/brands/{$brand->id}?tab=products");
        $this->assertModelMissing($product);
    }

    public function test_the_brand_page_lists_products_with_share_links(): void
    {
        $brand = Brand::factory()->create();
        $me = $this->member($brand, BrandRole::Copywriter);
        $product = BrandProduct::factory()->create(['brand_id' => $brand->id, 'name' => 'Beans', 'price' => 'Rp 90.000']);

        $this->actingAs($me)->get("/brands/{$brand->id}?tab=products")->assertInertia(fn ($page) => $page
            ->where('products.0.name', 'Beans')->where('products.0.price', 'Rp 90.000')
            ->where('products.0.share_url', "/brands/{$brand->id}/products/{$product->id}"));
    }
}

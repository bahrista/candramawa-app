<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    private function brandFor(User $owner, string $name = 'Acme'): Brand
    {
        $b = new Brand(['name' => $name]);
        $b->created_by = $owner->id;
        $b->save();

        return $b;
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/brands')->assertRedirect('/login');
        $this->post('/brands', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_user_with_temporary_password_cannot_use_brands(): void
    {
        $this->actingAs(User::factory()->mustChangePassword()->create())
            ->get('/brands')->assertRedirect('/profile');
    }

    public function test_user_creates_brand_and_becomes_its_owner(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->post('/brands', [
            'name' => '  Kopi Senja ', 'description' => 'Coffee', 'color' => '#FF8800',
        ])->assertRedirect('/brands');

        $b = Brand::first();
        $this->assertSame('Kopi Senja', $b->name);
        $this->assertSame('#ff8800', $b->color);
        $this->assertSame($u->id, $b->created_by);
    }

    public function test_default_color_applies_and_description_blank_is_null(): void
    {
        $this->actingAs(User::factory()->create())->post('/brands', ['name' => 'A', 'description' => '', 'color' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame('#2271b1', Brand::first()->color);
        $this->assertNull(Brand::first()->description);
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/brands', ['name' => '', 'color' => 'red'])
            ->assertSessionHasErrors(['name', 'color']);
    }

    public function test_created_by_cannot_be_forged_from_the_form(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($u)->post('/brands', ['name' => 'A', 'created_by' => $other->id]);

        $this->assertSame($u->id, Brand::first()->created_by);
    }

    public function test_user_sees_only_own_brands_and_superadmin_sees_all(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->brandFor($a, 'Alpha');
        $this->brandFor($b, 'Beta');

        $this->actingAs($a)->get('/brands')->assertInertia(fn ($page) => $page
            ->component('Brands/Index')->has('brands.data', 1)->where('brands.data.0.name', 'Alpha'));

        $this->actingAs(User::factory()->superadmin()->create())->get('/brands')
            ->assertInertia(fn ($page) => $page->has('brands.data', 2));
    }

    public function test_other_user_cannot_edit_update_or_view_logo_but_owner_and_superadmin_can(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $brand = $this->brandFor($owner);

        $this->actingAs($owner)->post("/brands/{$brand->id}", [
            '_method' => 'put', 'name' => 'Acme', 'logo' => UploadedFile::fake()->image('l.png'),
        ])->assertRedirect('/brands');
        $this->assertNotNull($brand->fresh()->logo_path);

        $this->actingAs($stranger);
        $this->get("/brands/{$brand->id}/edit")->assertForbidden();
        $this->put("/brands/{$brand->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->get("/brands/{$brand->id}/logo")->assertForbidden();
        $this->assertSame('Acme', $brand->fresh()->name);

        $this->actingAs($owner)->get("/brands/{$brand->id}/logo")->assertOk();
        $this->actingAs(User::factory()->superadmin()->create())
            ->get("/brands/{$brand->id}/edit")->assertOk();
    }

    public function test_logo_replace_and_remove_delete_old_files(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $this->actingAs($u)->post('/brands', ['name' => 'A', 'logo' => UploadedFile::fake()->image('1.png')]);
        $brand = Brand::first();
        $first = $brand->logo_path;
        Storage::disk('local')->assertExists($first);

        $this->post("/brands/{$brand->id}", ['_method' => 'put', 'name' => 'A', 'logo' => UploadedFile::fake()->image('2.jpg')]);
        Storage::disk('local')->assertMissing($first);
        $second = $brand->fresh()->logo_path;
        Storage::disk('local')->assertExists($second);

        $this->post("/brands/{$brand->id}", ['_method' => 'put', 'name' => 'A', 'remove_logo' => '1']);
        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_logo_rejects_non_images_and_oversize(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $this->post('/brands', ['name' => 'A', 'logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('logo');
        $this->post('/brands', ['name' => 'A', 'logo' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('logo');
        $this->assertSame(0, Brand::count());
    }
}

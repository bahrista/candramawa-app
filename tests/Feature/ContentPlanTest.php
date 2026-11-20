<?php

namespace Tests\Feature;

use App\Enums\BrandRole;
use App\Enums\ContentStatus;
use App\Models\Brand;
use App\Models\BrandContentPillar;
use App\Models\BrandKnowledgeItem;
use App\Models\BrandMember;
use App\Models\BrandProduct;
use App\Models\ContentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContentPlanTest extends TestCase
{
    use RefreshDatabase;

    private const FILE_A = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz123456/view?usp=sharing';

    private const FILE_B = 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJiHgFeDcBa987654/view';

    /** A brand created by $owner (who is also its Brand Owner, as in the app). */
    private function brandOf(User $owner): Brand
    {
        $brand = Brand::factory()->create(['created_by' => $owner->id]);
        $this->join($brand, $owner, BrandRole::BrandOwner);

        return $brand;
    }

    private function join(Brand $brand, User $user, BrandRole $role): User
    {
        BrandMember::create(['brand_id' => $brand->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function pillar(Brand $brand, string $name = 'Promotional'): BrandContentPillar
    {
        return BrandContentPillar::query()->where(['brand_id' => $brand->id, 'name' => $name])->first()
            ?? BrandContentPillar::factory()->create(['brand_id' => $brand->id, 'name' => $name]);
    }

    /** A complete payload for the "production" stage (media and captions are only needed for review). */
    private function payload(Brand $brand, array $override = []): array
    {
        return array_replace([
            'action' => 'save',
            'brand_id' => $brand->id,
            'title' => 'Launch teaser',
            'content_pillar_id' => $this->pillar($brand)->id,
            'content_types' => ['instagram_single', 'instagram_story'],
            'publish_at' => '2026-12-01T19:00',
            'deadline_mode' => 'auto',
            'deadline_at' => '',
            'multimedia_creator_id' => null,
            'copywriter_id' => null,
            'publisher_id' => null,
            'brief' => 'Show the new product.',
            'brief_references' => [['kind' => 'link', 'url' => 'https://www.instagram.com/p/abc/']],
            'posts' => [
                'instagram_single' => ['media_links' => [], 'caption' => null, 'tags' => [], 'note' => null],
                'instagram_story' => ['media_links' => [], 'caption' => null, 'tags' => [], 'note' => null],
            ],
        ], $override);
    }

    /** Same, but ready for review: media on every content type, captions where required. */
    private function reviewPayload(Brand $brand, array $override = []): array
    {
        return $this->payload($brand, array_replace([
            'action' => 'review',
            'posts' => [
                'instagram_single' => ['media_links' => [self::FILE_A], 'caption' => 'Hello', 'tags' => [], 'note' => null],
                'instagram_story' => ['media_links' => [self::FILE_B], 'caption' => null, 'tags' => [], 'note' => null],
            ],
        ], $override));
    }

    private function inProduction(User $manager, Brand $brand, array $attributes = [], array $types = ['instagram_single']): ContentPlan
    {
        return ContentPlan::factory()->inProduction()->withPosts($types)
            ->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id] + $attributes);
    }

    // ---- access ----------------------------------------------------------------------

    public function test_guests_and_users_with_temporary_password_cannot_use_content_plans(): void
    {
        $this->get('/content-plans')->assertRedirect('/login');
        $this->post('/content-plans', [])->assertRedirect('/login');

        $this->actingAs(User::factory()->mustChangePassword()->create())
            ->get('/content-plans')->assertRedirect('/profile');
    }

    public function test_create_page_offers_brands_the_user_may_plan_for_with_their_pillars_and_team(): void
    {
        $me = User::factory()->create();
        $mine = $this->brandOf($me);
        $mine->update(['name' => 'Alpha']); // brands are listed by name
        $this->pillar($mine, 'Educational');
        $designer = $this->join($mine, User::factory()->create(['name' => 'Dina']), BrandRole::MultimediaCreator);
        $this->join($mine, User::factory()->inactive()->create(), BrandRole::Publisher);
        BrandKnowledgeItem::factory()->create(['brand_id' => $mine->id, 'title' => 'Tone guide']);
        BrandProduct::factory()->create(['brand_id' => $mine->id, 'name' => 'Latte']);
        BrandProduct::factory()->create(['brand_id' => $mine->id, 'name' => 'Old item', 'is_active' => false]);

        $theirs = $this->brandOf(User::factory()->create());
        $theirs->update(['name' => 'Beta']);
        $this->join($theirs, $me, BrandRole::ContentManager);
        $designOnly = $this->brandOf(User::factory()->create());
        $this->join($designOnly, $me, BrandRole::MultimediaCreator);
        $this->brandOf(User::factory()->create());

        $this->actingAs($me)->get('/content-plans/create')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ContentPlans/Editor')
            ->where('plan', null)
            ->has('brands', 2)
            ->where('brands.0.id', $mine->id)
            ->where('brands.0.pillars.0.name', 'Educational')
            ->where('brands.0.team.multimedia_creator.0.id', $designer->id)
            ->where('brands.0.team.publisher', [])
            ->where('brands.0.knowledge.0.title', 'Tone guide')
            ->has('brands.0.products', 1)
            ->where('brands.1.id', $theirs->id)
            ->where('abilities.actions', ['save', 'production', 'review'])
            ->has('contentTypes', 4));

        $this->actingAs(User::factory()->superadmin()->create())->get('/content-plans/create')
            ->assertInertia(fn ($page) => $page->has('brands', 4));
    }

    public function test_create_page_can_preselect_a_brand_from_the_link(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $foreign = $this->brandOf(User::factory()->create());

        $this->actingAs($me)->get("/content-plans/create?brand={$brand->id}")
            ->assertInertia(fn ($page) => $page->where('prefillBrandId', $brand->id));
        $this->get("/content-plans/create?brand={$foreign->id}")->assertInertia(fn ($page) => $page->where('prefillBrandId', null));
    }

    public function test_a_brands_content_manager_can_plan_for_it_but_other_roles_cannot(): void
    {
        $brand = $this->brandOf(User::factory()->create());
        $manager = $this->join($brand, User::factory()->create(), BrandRole::ContentManager);
        $designer = $this->join($brand, User::factory()->create(), BrandRole::MultimediaCreator);

        $this->actingAs($manager)->post('/content-plans', $this->payload($brand))->assertSessionHasNoErrors();
        $this->assertSame($manager->id, ContentPlan::firstOrFail()->content_manager_id);

        $this->actingAs($designer)->post('/content-plans', $this->payload($brand))->assertSessionHasErrors('brand_id');
        $this->assertSame(1, ContentPlan::count());
    }

    // ---- drafts ----------------------------------------------------------------------

    public function test_a_draft_needs_only_a_brand_and_a_title(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        $this->actingAs($me)->post('/content-plans', [
            'action' => 'save', 'brand_id' => $brand->id, 'title' => 'Idea', 'deadline_mode' => 'auto',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $plan = ContentPlan::firstOrFail();
        $this->assertSame(ContentStatus::Draft, $plan->status);
        $this->assertSame($me->id, $plan->content_manager_id);
        $this->assertNull($plan->publish_at);
        $this->assertNull($plan->content_pillar_id);
    }

    public function test_content_manager_and_status_cannot_be_forged(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', [
            'action' => 'save', 'brand_id' => $this->brandOf($me)->id, 'title' => 'Idea', 'deadline_mode' => 'auto',
            'content_manager_id' => $other->id, 'status' => 'in_review',
        ]);

        $plan = ContentPlan::firstOrFail();
        $this->assertSame($me->id, $plan->content_manager_id);
        $this->assertSame(ContentStatus::Draft, $plan->status);
    }

    public function test_draft_still_checks_the_basics(): void
    {
        $me = User::factory()->create();
        $foreign = $this->brandOf(User::factory()->create());

        $this->actingAs($me)->post('/content-plans', ['action' => 'save', 'deadline_mode' => 'auto'])
            ->assertSessionHasErrors(['brand_id', 'title']);

        $this->post('/content-plans', $this->payload($foreign))->assertSessionHasErrors('brand_id');
        $this->post('/content-plans', $this->payload($this->brandOf($me), ['publish_at' => 'tomorrow']))
            ->assertSessionHasErrors('publish_at');
        $this->assertSame(0, ContentPlan::count());
    }

    public function test_superadmin_can_plan_for_any_brand(): void
    {
        $brand = $this->brandOf(User::factory()->create());

        $this->actingAs(User::factory()->superadmin()->create())
            ->post('/content-plans', $this->payload($brand))->assertSessionHasNoErrors();

        $this->assertSame(1, ContentPlan::count());
    }

    // ---- production ------------------------------------------------------------------

    public function test_submitting_to_production_requires_a_complete_brief(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', [
            'action' => 'production', 'brand_id' => $this->brandOf($me)->id, 'title' => 'Idea', 'deadline_mode' => 'auto',
        ])->assertSessionHasErrors(['content_pillar_id', 'content_types', 'publish_at', 'brief']);

        $this->assertSame(0, ContentPlan::count());
    }

    public function test_submitting_to_production_stores_utc_times_auto_deadline_pillar_and_posts(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $pillar = $this->pillar($brand);

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
            'action' => 'production',
            'copywriter_id' => $writer->id,
            'content_types' => ['instagram_story', 'instagram_single'],
            'posts' => [
                'instagram_single' => ['media_links' => ['', self::FILE_A], 'caption' => 'Hi', 'tags' => ['launch', '@Brand', '#Launch', ' '], 'note' => 'Use the blue logo'],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect('/content-plans');

        $plan = ContentPlan::with('posts')->firstOrFail();
        $this->assertSame(ContentStatus::InProduction, $plan->status);
        $this->assertNotNull($plan->production_started_at);
        $this->assertSame($pillar->id, $plan->content_pillar_id);
        $this->assertSame('2026-12-01 12:00:00', $plan->publish_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-30 12:00:00', $plan->deadline_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($writer->id, $plan->copywriter_id);
        $this->assertSame(['instagram_single', 'instagram_story'], $plan->contentTypeKeys());

        $single = $plan->posts->firstWhere('content_type', 'instagram_single');
        $this->assertSame([self::FILE_A], $single->media_links);
        $this->assertSame(['#launch', '@Brand'], $single->tags);
        $this->assertSame('Use the blue logo', $single->note);
    }

    public function test_manual_deadline_is_kept_but_may_not_be_after_the_publish_date(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
            'deadline_mode' => 'manual', 'deadline_at' => '2026-12-02T10:00',
        ]))->assertSessionHasErrors('deadline_at');

        $this->post('/content-plans', $this->payload($brand, [
            'action' => 'production', 'deadline_mode' => 'manual', 'deadline_at' => '',
        ]))->assertSessionHasErrors('deadline_at');

        $this->post('/content-plans', $this->payload($brand, [
            'deadline_mode' => 'manual', 'deadline_at' => '2026-11-28T09:30',
        ]))->assertSessionHasNoErrors();

        $plan = ContentPlan::firstOrFail();
        $this->assertSame('manual', $plan->deadline_mode);
        $this->assertSame('2026-11-28 02:30:00', $plan->deadline_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_the_pillar_must_be_one_of_the_brands_active_pillars(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $other = $this->brandOf($me);
        $archived = BrandContentPillar::factory()->archived()->create(['brand_id' => $brand->id]);

        $this->actingAs($me);
        $this->post('/content-plans', $this->payload($brand, ['content_pillar_id' => $this->pillar($other)->id]))
            ->assertSessionHasErrors('content_pillar_id');
        $this->post('/content-plans', $this->payload($brand, ['content_pillar_id' => $archived->id]))
            ->assertSessionHasErrors('content_pillar_id');
        $this->post('/content-plans', $this->payload($brand, ['content_pillar_id' => 9999]))
            ->assertSessionHasErrors('content_pillar_id');

        // A plan that already uses a pillar keeps it after the pillar is archived.
        $plan = $this->inProduction($me, $brand, ['content_pillar_id' => $archived->id]);
        $this->put("/content-plans/{$plan->id}", $this->payload($brand, ['content_pillar_id' => $archived->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_people_are_assigned_from_the_brands_team_with_the_matching_role(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $designer = $this->join($brand, User::factory()->create(), BrandRole::MultimediaCreator);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $outsider = User::factory()->create();
        $inactive = $this->join($brand, User::factory()->inactive()->create(), BrandRole::Publisher);

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
            'multimedia_creator_id' => $designer->id, 'copywriter_id' => $writer->id,
        ]))->assertSessionHasNoErrors();

        $this->post('/content-plans', $this->payload($brand, ['multimedia_creator_id' => $outsider->id]))->assertSessionHasErrors('multimedia_creator_id');
        $this->post('/content-plans', $this->payload($brand, ['multimedia_creator_id' => $writer->id]))->assertSessionHasErrors('multimedia_creator_id'); // wrong role
        $this->post('/content-plans', $this->payload($brand, ['publisher_id' => $inactive->id]))->assertSessionHasErrors('publisher_id');
        $this->assertSame(1, ContentPlan::count());
    }

    public function test_someone_already_assigned_may_stay_after_leaving_the_team(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $former = User::factory()->create();
        $plan = $this->inProduction($me, $brand, ['publisher_id' => $former->id]);

        $this->actingAs($me)->put("/content-plans/{$plan->id}", $this->payload($brand, ['publisher_id' => $former->id]))
            ->assertSessionHasNoErrors();

        $this->get("/content-plans/{$plan->id}")->assertInertia(fn ($page) => $page
            ->where('plan.assignees.publisher_id.id', $former->id));
    }

    // ---- review ----------------------------------------------------------------------

    public function test_review_needs_media_everywhere_and_captions_except_for_stories(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), ['action' => 'review']))
            ->assertSessionHasErrors(['posts.instagram_single.media_links', 'posts.instagram_single.caption', 'posts.instagram_story.media_links'])
            ->assertSessionDoesntHaveErrors('posts.instagram_story.caption');
    }

    public function test_media_rules_follow_the_content_type(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $post = fn (array $links, ?string $caption = 'x') => ['media_links' => $links, 'caption' => $caption, 'tags' => [], 'note' => null];

        $this->actingAs($me)->post('/content-plans', $this->reviewPayload($brand, [
            'content_types' => ['instagram_carousel', 'instagram_reel', 'tiktok_carousel', 'other_flyer'],
            'posts' => [
                'instagram_carousel' => $post([self::FILE_A]),                     // needs 2
                'instagram_reel' => $post([self::FILE_A, self::FILE_B]),           // allows 1
                'tiktok_carousel' => $post([self::FILE_A]),                        // needs 2
                'other_flyer' => $post([self::FILE_A, self::FILE_B], null),        // 1 to 5, caption optional
            ],
        ]))->assertSessionHasErrors(['posts.instagram_carousel.media_links', 'posts.instagram_reel.media_links', 'posts.tiktok_carousel.media_links'])
            ->assertSessionDoesntHaveErrors(['posts.other_flyer.media_links', 'posts.other_flyer.caption']);

        $this->post('/content-plans', $this->reviewPayload($brand, [
            'content_types' => ['instagram_carousel', 'facebook_feed', 'facebook_story', 'other_sales_kit'],
            'posts' => [
                'instagram_carousel' => $post([self::FILE_A, self::FILE_B]),
                'facebook_feed' => $post([self::FILE_A, self::FILE_B]),
                'facebook_story' => $post([self::FILE_A], null),
                'other_sales_kit' => $post([self::FILE_A], null),
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(ContentStatus::InReview, ContentPlan::firstOrFail()->status);
    }

    public function test_caption_limits_differ_per_platform(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $long = str_repeat('a', 3000);
        $post = fn (string $caption) => ['media_links' => [], 'caption' => $caption, 'tags' => [], 'note' => null];

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
            'content_types' => ['instagram_single', 'facebook_feed'],
            'posts' => ['instagram_single' => $post($long), 'facebook_feed' => $post($long)],
        ]))->assertSessionHasErrors('posts.instagram_single.caption')
            ->assertSessionDoesntHaveErrors('posts.facebook_feed.caption');
    }

    public function test_media_links_must_be_google_drive_links(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        foreach (['https://example.com/a.png', 'https://drive.google.com.evil.example/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz123456/view', 'javascript:alert(1)'] as $bad) {
            $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
                'posts' => ['instagram_single' => ['media_links' => [$bad], 'caption' => null, 'tags' => [], 'note' => null]],
            ]))->assertSessionHasErrors('posts.instagram_single.media_links.0');
        }
    }

    public function test_tags_are_checked(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), [
            'posts' => ['instagram_single' => ['media_links' => [], 'caption' => null, 'tags' => ['bad!tag'], 'note' => null]],
        ]))->assertSessionHasErrors('posts.instagram_single.tags.0');
    }

    // ---- content types ---------------------------------------------------------------

    public function test_only_known_content_types_are_accepted(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        foreach (['instagram_feed', 'youtube_short', 'x'] as $unknown) {
            $this->actingAs($me)->post('/content-plans', $this->payload($brand, ['content_types' => [$unknown]]))
                ->assertSessionHasErrors('content_types.0');
        }

        $this->post('/content-plans', $this->payload($brand, ['content_types' => ['instagram_story', 'instagram_story']]))
            ->assertSessionHasErrors('content_types.0');
    }

    public function test_unticking_a_content_type_removes_its_details(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $plan = ContentPlan::factory()->withPosts(['instagram_single', 'tiktok_video'])
            ->create(['content_manager_id' => $me->id, 'brand_id' => $brand->id]);

        $this->actingAs($me)->put("/content-plans/{$plan->id}", $this->payload($brand, [
            'content_types' => ['tiktok_video'], 'posts' => [],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['tiktok_video'], $plan->fresh()->contentTypeKeys());
    }

    public function test_once_past_draft_the_brief_must_stay_complete(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $plan = $this->inProduction($me, $brand);

        $this->actingAs($me)->put("/content-plans/{$plan->id}", $this->payload($brand, ['brief' => '', 'content_pillar_id' => null]))
            ->assertSessionHasErrors(['brief', 'content_pillar_id']);
    }

    // ---- references in the brief -----------------------------------------------------

    public function test_references_can_be_links_knowledge_entries_and_products_and_come_back_resolved(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $kb = BrandKnowledgeItem::factory()->create(['brand_id' => $brand->id, 'title' => 'Tone of voice']);
        $product = BrandProduct::factory()->create(['brand_id' => $brand->id, 'name' => 'Signature latte']);

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, ['brief_references' => [
            ['kind' => 'link', 'url' => 'https://example.com/ref'],
            ['kind' => 'link', 'url' => ''],
            ['kind' => 'knowledge', 'id' => $kb->id],
            ['kind' => 'knowledge', 'id' => $kb->id],
            ['kind' => 'product', 'id' => $product->id],
        ]]))->assertSessionHasNoErrors();

        $plan = ContentPlan::firstOrFail();
        $this->assertSame([
            ['kind' => 'link', 'url' => 'https://example.com/ref'],
            ['kind' => 'knowledge', 'id' => $kb->id],
            ['kind' => 'product', 'id' => $product->id],
        ], $plan->brief_references);

        $kb->update(['title' => 'Voice and tone']);

        $this->get("/content-plans/{$plan->id}")->assertInertia(fn ($page) => $page
            ->where('plan.brief_references.1.label', 'Voice and tone')
            ->where('plan.brief_references.1.url', "/brands/{$brand->id}/knowledge/{$kb->id}")
            ->where('plan.brief_references.2.label', 'Signature latte')
            ->where('plan.brief_references.2.url', "/brands/{$brand->id}/products/{$product->id}")
            ->where('plan.brief_references.0.url', 'https://example.com/ref'));

        $kb->delete();

        $this->get("/content-plans/{$plan->id}")->assertInertia(fn ($page) => $page
            ->where('plan.brief_references.1.missing', true)->where('plan.brief_references.1.label', 'Removed entry'));
    }

    public function test_references_must_belong_to_the_plans_brand_and_links_must_be_urls(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $foreign = BrandKnowledgeItem::factory()->create(['brand_id' => $this->brandOf($me)->id]);

        $this->actingAs($me)->post('/content-plans', $this->payload($brand, ['brief_references' => [['kind' => 'knowledge', 'id' => $foreign->id]]]))
            ->assertSessionHasErrors('brief_references.0.id');
        $this->post('/content-plans', $this->payload($brand, ['brief_references' => [['kind' => 'product', 'id' => 99999]]]))
            ->assertSessionHasErrors('brief_references.0.id');
        $this->post('/content-plans', $this->payload($brand, ['brief_references' => [['kind' => 'link', 'url' => 'javascript:alert(1)']]]))
            ->assertSessionHasErrors('brief_references.0.url');
        $this->post('/content-plans', $this->payload($brand, ['brief_references' => [['kind' => 'wiki', 'url' => 'https://x.test']]]))
            ->assertSessionHasErrors('brief_references.0.kind');
        $this->assertSame(0, ContentPlan::count());
    }

    // ---- who sees what ---------------------------------------------------------------

    public function test_a_draft_is_private_to_its_manager_until_it_is_submitted(): void
    {
        $manager = User::factory()->create();
        $writer = User::factory()->create();
        $brand = $this->brandOf($manager);
        $plan = ContentPlan::factory()->create([
            'content_manager_id' => $manager->id, 'copywriter_id' => $writer->id, 'brand_id' => $brand->id,
        ]);

        $this->actingAs($writer)->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 0));
        $this->get("/content-plans/{$plan->id}")->assertForbidden();

        $plan->forceFill(['status' => ContentStatus::InProduction])->save(); // status is not mass assignable

        $this->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 1));
        $this->get("/content-plans/{$plan->id}")->assertOk();
    }

    public function test_every_member_of_the_brand_team_sees_submitted_plans_but_never_drafts(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $publisher = $this->join($brand, User::factory()->create(), BrandRole::Publisher);
        $draft = ContentPlan::factory()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id]);
        $live = $this->inProduction($manager, $brand);

        $this->actingAs($publisher)->get('/content-plans')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('plans.data.0.id', $live->id));
        $this->get("/content-plans/{$live->id}")->assertOk();
        $this->get("/content-plans/{$draft->id}")->assertForbidden();

        // ... but only to look: they are not assigned, so nothing is editable.
        $this->get("/content-plans/{$live->id}")->assertInertia(fn ($p) => $p
            ->where('abilities.editBrief', false)->where('abilities.editPosts', false)->where('abilities.actions', []));
        $this->put("/content-plans/{$live->id}", ['action' => 'save'])->assertForbidden();
    }

    public function test_a_brand_owner_reviews_what_others_submitted_but_does_not_see_their_drafts(): void
    {
        $creator = User::factory()->create();
        $brand = $this->brandOf($creator);
        $owner = $this->join($brand, User::factory()->create(), BrandRole::BrandOwner);
        $manager = $this->join($brand, User::factory()->create(), BrandRole::ContentManager);
        $draft = ContentPlan::factory()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id]);
        $inReview = ContentPlan::factory()->inReview()->withPosts()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id]);

        $this->actingAs($owner)->get("/content-plans/{$draft->id}")->assertForbidden();
        $this->get("/content-plans/{$inReview->id}")->assertInertia(fn ($p) => $p
            ->where('abilities.editBrief', true)->where('abilities.actions', ['save', 'revise']));

        // the brand's creator has the same powers
        $this->actingAs($creator)->get("/content-plans/{$inReview->id}")->assertInertia(fn ($p) => $p->where('abilities.actions', ['save', 'revise']));
    }

    public function test_strangers_are_locked_out_and_superadmin_sees_everything(): void
    {
        $plan = ContentPlan::factory()->inProduction()->create();

        $this->actingAs(User::factory()->create())->get("/content-plans/{$plan->id}")->assertForbidden();
        $this->put("/content-plans/{$plan->id}", [])->assertForbidden();

        $this->actingAs(User::factory()->superadmin()->create())->get("/content-plans/{$plan->id}")->assertOk();
    }

    public function test_brand_logo_opens_for_the_team_and_people_in_a_plan_but_the_brand_stays_private(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('brand-logos/l.png', 'png');
        $owner = User::factory()->create();
        $brand = Brand::factory()->create(['created_by' => $owner->id, 'logo_path' => 'brand-logos/l.png']);
        $teammate = $this->join($brand, User::factory()->create(), BrandRole::Publisher);
        $legacy = User::factory()->create(); // assigned before teams existed, not on the team
        $stranger = User::factory()->create();
        ContentPlan::factory()->inProduction()->create(['brand_id' => $brand->id, 'multimedia_creator_id' => $legacy->id]);

        foreach ([$teammate, $legacy] as $person) {
            $this->actingAs($person)->get("/brands/{$brand->id}/logo")->assertOk();
            $this->get("/brands/{$brand->id}/edit")->assertForbidden();
            $this->put("/brands/{$brand->id}", ['name' => 'Hacked'])->assertForbidden();
        }

        $this->actingAs($stranger)->get("/brands/{$brand->id}/logo")->assertForbidden();
    }

    // ---- production team -------------------------------------------------------------

    public function test_assignee_fills_post_details_but_cannot_touch_the_brief(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $other = $this->brandOf($manager);
        $designer = $this->join($brand, User::factory()->create(), BrandRole::MultimediaCreator);
        $plan = $this->inProduction($manager, $brand, ['multimedia_creator_id' => $designer->id, 'title' => 'Original']);

        $this->actingAs($designer)->put("/content-plans/{$plan->id}", [
            'action' => 'save',
            'title' => 'Hacked', 'brand_id' => $other->id, 'content_types' => ['instagram_single', 'instagram_reel'],
            'posts' => ['instagram_single' => ['media_links' => [self::FILE_A], 'caption' => 'From the designer', 'tags' => ['x'], 'note' => null]],
        ])->assertSessionHasNoErrors();

        $plan->refresh()->load('posts');
        $this->assertSame('Original', $plan->title);
        $this->assertSame($brand->id, $plan->brand_id);
        $this->assertSame(['instagram_single'], $plan->contentTypeKeys());
        $this->assertSame('From the designer', $plan->posts->first()->caption);
        $this->assertSame([self::FILE_A], $plan->posts->first()->media_links);
    }

    public function test_assignee_can_submit_to_review_and_is_then_locked_out_of_editing(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $plan = $this->inProduction($manager, $brand, ['copywriter_id' => $writer->id]);

        $post = ['instagram_single' => ['media_links' => [self::FILE_A], 'caption' => 'Ready', 'tags' => [], 'note' => null]];

        $this->actingAs($writer)->put("/content-plans/{$plan->id}", ['action' => 'review', 'posts' => $post])
            ->assertSessionHasNoErrors()->assertRedirect('/content-plans');

        $plan->refresh();
        $this->assertSame(ContentStatus::InReview, $plan->status);
        $this->assertNotNull($plan->review_submitted_at);

        $this->put("/content-plans/{$plan->id}", ['action' => 'save', 'posts' => $post])->assertForbidden();
        $this->put("/content-plans/{$plan->id}", ['action' => 'revise', 'posts' => $post])->assertForbidden();
    }

    public function test_review_from_the_team_still_needs_media_and_caption(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $plan = $this->inProduction($manager, $brand, ['copywriter_id' => $writer->id]);

        $this->actingAs($writer)->put("/content-plans/{$plan->id}", ['action' => 'review', 'posts' => ['instagram_single' => ['media_links' => [], 'caption' => '']]])
            ->assertSessionHasErrors(['posts.instagram_single.media_links', 'posts.instagram_single.caption']);

        $this->assertSame(ContentStatus::InProduction, $plan->fresh()->status);
    }

    public function test_assignees_cannot_submit_to_production(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $plan = $this->inProduction($manager, $brand, ['copywriter_id' => $writer->id]);

        $this->actingAs($writer)->put("/content-plans/{$plan->id}", ['action' => 'production'])->assertForbidden();
    }

    // ---- workflow --------------------------------------------------------------------

    public function test_manager_and_brand_owner_can_send_a_plan_in_review_back_to_production(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $owner = $this->join($brand, User::factory()->create(), BrandRole::BrandOwner);

        foreach ([$manager, $owner] as $reviewer) {
            $plan = ContentPlan::factory()->inReview()->withPosts()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id]);

            $this->actingAs($reviewer)->put("/content-plans/{$plan->id}", $this->reviewPayload($brand, [
                'action' => 'revise', 'content_types' => ['instagram_single'],
                'posts' => ['instagram_single' => ['media_links' => [self::FILE_A], 'caption' => 'Change this', 'tags' => [], 'note' => 'Please redo']],
            ]))->assertSessionHasNoErrors();

            $plan->refresh();
            $this->assertSame(ContentStatus::InProduction, $plan->status);
            $this->assertNull($plan->review_submitted_at);
        }
    }

    public function test_buttons_only_work_at_the_right_stage(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $inProduction = $this->inProduction($me, $brand);
        $draft = ContentPlan::factory()->create(['content_manager_id' => $me->id, 'brand_id' => $brand->id]);

        $this->actingAs($me);
        $this->put("/content-plans/{$inProduction->id}", $this->payload($brand, ['action' => 'production']))->assertForbidden();
        $this->put("/content-plans/{$draft->id}", $this->payload($brand, ['action' => 'revise']))->assertForbidden();
        $this->put("/content-plans/{$draft->id}", $this->payload($brand, ['action' => 'publish']))->assertForbidden();
        $this->post('/content-plans', $this->payload($brand, ['action' => 'revise']))->assertForbidden();
    }

    public function test_abilities_sent_to_the_page_match_the_stage(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $draft = ContentPlan::factory()->create(['content_manager_id' => $me->id, 'brand_id' => $brand->id]);
        $review = ContentPlan::factory()->inReview()->create(['content_manager_id' => $me->id, 'brand_id' => $brand->id]);

        $this->actingAs($me)->get("/content-plans/{$draft->id}")->assertInertia(fn ($p) => $p
            ->where('abilities.actions', ['save', 'production', 'review'])->where('abilities.canDelete', true));

        $this->get("/content-plans/{$review->id}")->assertInertia(fn ($p) => $p
            ->where('abilities.actions', ['save', 'revise'])->where('abilities.canDelete', false));
    }

    // ---- share links -----------------------------------------------------------------

    public function test_a_shared_link_sends_guests_to_log_in_and_then_back_to_it(): void
    {
        $manager = User::factory()->create(['username' => 'ani', 'password' => 'secret123']);
        $plan = $this->inProduction($manager, $this->brandOf($manager));
        $link = url("/content-plans/{$plan->id}?view=publisher");

        $this->get("/content-plans/{$plan->id}?view=publisher")->assertRedirect('/login')->assertSessionHas('url.intended', $link);

        $this->withSession(['url.intended' => $link])->post('/login', ['username' => 'ani', 'password' => 'secret123'])
            ->assertRedirect($link);
    }

    public function test_the_view_in_a_shared_link_is_passed_to_the_page_and_junk_is_ignored(): void
    {
        $me = User::factory()->create();
        $plan = $this->inProduction($me, $this->brandOf($me));

        $this->actingAs($me);
        foreach (['production', 'approval', 'publisher'] as $view) {
            $this->get("/content-plans/{$plan->id}?view=$view")->assertInertia(fn ($p) => $p->where('view', $view));
        }
        $this->get("/content-plans/{$plan->id}?view=hacker")->assertInertia(fn ($p) => $p->where('view', null));
        $this->get("/content-plans/{$plan->id}?view[]=x")->assertOk();
    }

    public function test_someone_the_link_was_not_meant_for_sees_a_friendly_page(): void
    {
        $plan = $this->inProduction($manager = User::factory()->create(), $this->brandOf($manager));

        $this->actingAs(User::factory()->create())->get("/content-plans/{$plan->id}")
            ->assertForbidden()
            ->assertInertia(fn ($p) => $p->component('Error')->where('status', 403));

        $this->get('/content-plans/999999')->assertNotFound()->assertInertia(fn ($p) => $p->component('Error')->where('status', 404));
    }

    // ---- delete ----------------------------------------------------------------------

    public function test_only_the_manager_can_delete_and_only_drafts(): void
    {
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $writer = $this->join($brand, User::factory()->create(), BrandRole::Copywriter);
        $owner = $this->join($brand, User::factory()->create(), BrandRole::BrandOwner);
        $draft = ContentPlan::factory()->withPosts()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id, 'copywriter_id' => $writer->id]);
        $live = $this->inProduction($manager, $brand, ['copywriter_id' => $writer->id]);

        $this->actingAs($writer)->delete("/content-plans/{$draft->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/content-plans/{$draft->id}")->assertForbidden();

        $this->actingAs($manager)->delete("/content-plans/{$live->id}")->assertForbidden();
        $this->delete("/content-plans/{$draft->id}")->assertRedirect('/content-plans');

        $this->assertModelMissing($draft);
        $this->assertDatabaseCount('content_plan_posts', 1);
        $this->assertModelExists($live);
    }

    // ---- all content table -----------------------------------------------------------

    public function test_the_table_filters_sorts_and_counts(): void
    {
        $me = User::factory()->create();
        $a = $this->brandOf($me);
        $b = $this->brandOf($me);
        $mine = ContentPlan::factory()->withPosts(['instagram_reel'])->create(['content_manager_id' => $me->id, 'brand_id' => $a->id, 'title' => 'Alpha reel', 'publish_at' => '2026-12-05 03:00:00']);
        ContentPlan::factory()->inProduction()->withPosts(['tiktok_video'])->create(['content_manager_id' => $me->id, 'brand_id' => $b->id, 'title' => 'Beta post', 'publish_at' => '2026-12-01 03:00:00']);
        ContentPlan::factory()->inReview()->create(['content_manager_id' => $me->id, 'brand_id' => $b->id, 'title' => 'Gamma', 'publish_at' => null]);
        ContentPlan::factory()->inProduction()->create(['title' => 'Not visible']); // somebody else's brand

        $this->actingAs($me);

        $this->get('/content-plans')->assertInertia(fn ($p) => $p
            ->has('plans.data', 3)
            ->where('counts', ['all' => 3, 'draft' => 1, 'in_production' => 1, 'in_review' => 1])
            ->where('plans.data.0.title', 'Alpha reel')   // newest publish date first, no date last
            ->where('plans.data.2.title', 'Gamma')
            ->has('contentTypes', 4));

        $this->get('/content-plans?sort=publish&dir=asc')->assertInertia(fn ($p) => $p
            ->where('plans.data.0.title', 'Beta post')->where('plans.data.2.title', 'Gamma'));

        $this->get('/content-plans?status=draft')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('counts.all', 3));
        $this->get('/content-plans?brand='.$b->id)->assertInertia(fn ($p) => $p->has('plans.data', 2));
        $this->get('/content-plans?content_type=instagram_reel')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('plans.data.0.id', $mine->id)->where('plans.data.0.content_types', ['instagram_reel']));
        $this->get('/content-plans?content_type=tiktok_video')->assertInertia(fn ($p) => $p->has('plans.data', 1)->where('plans.data.0.title', 'Beta post'));
        $this->get('/content-plans?search=beta')->assertInertia(fn ($p) => $p->has('plans.data', 1));
        $this->get('/content-plans?from=2026-12-02&to=2026-12-31')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('plans.data.0.title', 'Alpha reel'));
        $this->get('/content-plans?status=nonsense&sort=hack&dir=sideways&brand[]=1&content_type=nope')->assertOk();
    }

    public function test_assigned_to_me_filter(): void
    {
        $me = User::factory()->create();
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        $this->join($brand, $me, BrandRole::Publisher);
        $this->inProduction($manager, $brand, ['publisher_id' => $me->id]);
        $this->inProduction($manager, $brand);

        $this->actingAs($me)->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 2)); // the whole brand team sees both
        $this->get('/content-plans?mine=1')->assertInertia(fn ($p) => $p->has('plans.data', 1));
    }

    public function test_rows_carry_display_ready_dates_pillar_and_overdue_flag(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        ContentPlan::factory()->create([
            'content_manager_id' => $me->id, 'brand_id' => $brand->id, 'content_pillar_id' => $this->pillar($brand)->id,
            'publish_at' => '2026-12-01 12:00:00', 'deadline_at' => now()->subDay(),
        ]);

        $this->actingAs($me)->get('/content-plans')->assertInertia(fn ($p) => $p
            ->where('plans.data.0.publish_label', 'Tue, 1 Dec 2026 · 19:00')
            ->where('plans.data.0.content_pillar', 'Promotional')
            ->where('plans.data.0.is_overdue', true)
            ->where('plans.data.0.team.Content Manager.id', $me->id));
    }
}

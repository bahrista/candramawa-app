<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Models\Brand;
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

    private function brandOf(User $user): Brand
    {
        return Brand::factory()->create(['created_by' => $user->id]);
    }

    /** A complete, valid payload for the "production" stage (media/caption not needed yet). */
    private function payload(Brand $brand, array $override = []): array
    {
        return array_replace([
            'action' => 'save',
            'brand_id' => $brand->id,
            'title' => 'Launch teaser',
            'content_pillar' => 'Promotional',
            'format' => 'Video',
            'post_types' => ['instagram_feed', 'instagram_story'],
            'publish_at' => '2026-12-01T19:00',
            'deadline_mode' => 'auto',
            'deadline_at' => '',
            'multimedia_creator_id' => null,
            'copywriter_id' => null,
            'publisher_id' => null,
            'brief' => 'Show the new product.',
            'reference_links' => ['https://www.instagram.com/p/abc/'],
            'posts' => [
                'instagram_feed' => ['media_links' => [], 'caption' => null, 'tags' => [], 'note' => null],
                'instagram_story' => ['media_links' => [], 'caption' => null, 'tags' => [], 'note' => null],
            ],
        ], $override);
    }

    /** Same, but ready for review: media on every post, captions where required. */
    private function reviewPayload(Brand $brand, array $override = []): array
    {
        return $this->payload($brand, array_replace([
            'action' => 'review',
            'posts' => [
                'instagram_feed' => ['media_links' => [self::FILE_A], 'caption' => 'Hello', 'tags' => [], 'note' => null],
                'instagram_story' => ['media_links' => [self::FILE_B], 'caption' => null, 'tags' => [], 'note' => null],
            ],
        ], $override));
    }

    // ---- access ----------------------------------------------------------------------

    public function test_guests_and_users_with_temporary_password_cannot_use_content_plans(): void
    {
        $this->get('/content-plans')->assertRedirect('/login');
        $this->post('/content-plans', [])->assertRedirect('/login');

        $this->actingAs(User::factory()->mustChangePassword()->create())
            ->get('/content-plans')->assertRedirect('/profile');
    }

    public function test_create_page_only_offers_my_own_brands_but_superadmin_gets_all(): void
    {
        $me = User::factory()->create();
        $mine = $this->brandOf($me);
        $this->brandOf(User::factory()->create());

        $this->actingAs($me)->get('/content-plans/create')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ContentPlans/Editor')
            ->has('brands', 1)->where('brands.0.id', $mine->id)
            ->where('plan', null)
            ->where('abilities.actions', ['save', 'production', 'review']));

        $this->actingAs(User::factory()->superadmin()->create())->get('/content-plans/create')
            ->assertInertia(fn ($page) => $page->has('brands', 2));
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
        ])->assertSessionHasErrors(['content_pillar', 'format', 'post_types', 'publish_at', 'brief']);

        $this->assertSame(0, ContentPlan::count());
    }

    public function test_submitting_to_production_stores_utc_times_auto_deadline_and_posts(): void
    {
        $me = User::factory()->create();
        $writer = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), [
            'action' => 'production',
            'copywriter_id' => $writer->id,
            'post_types' => ['instagram_story', 'instagram_feed'],
            'posts' => [
                'instagram_feed' => ['media_links' => ['', self::FILE_A], 'caption' => 'Hi', 'tags' => ['launch', '@Brand', '#Launch', ' '], 'note' => 'Use the blue logo'],
            ],
        ]))->assertSessionHasNoErrors()->assertRedirect('/content-plans');

        $plan = ContentPlan::with('posts')->firstOrFail();
        $this->assertSame(ContentStatus::InProduction, $plan->status);
        $this->assertNotNull($plan->production_started_at);
        $this->assertSame('2026-12-01 12:00:00', $plan->publish_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-30 12:00:00', $plan->deadline_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($writer->id, $plan->copywriter_id);
        $this->assertSame(['https://www.instagram.com/p/abc/'], $plan->reference_links);
        $this->assertSame(['instagram_feed', 'instagram_story'], $plan->postTypeValues());

        $feed = $plan->posts->firstWhere('post_type', PostType::InstagramFeed);
        $this->assertSame([self::FILE_A], $feed->media_links);
        $this->assertSame(['#launch', '@Brand'], $feed->tags);
        $this->assertSame('Use the blue logo', $feed->note);
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

    public function test_inactive_users_cannot_be_assigned(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), [
            'publisher_id' => User::factory()->inactive()->create()->id,
        ]))->assertSessionHasErrors('publisher_id');
    }

    // ---- review ----------------------------------------------------------------------

    public function test_review_needs_media_everywhere_and_captions_except_for_stories(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), ['action' => 'review']))
            ->assertSessionHasErrors(['posts.instagram_feed.media_links', 'posts.instagram_feed.caption', 'posts.instagram_story.media_links'])
            ->assertSessionDoesntHaveErrors('posts.instagram_story.caption');
    }

    public function test_a_carousel_needs_two_links_and_other_types_only_one(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        $this->actingAs($me)->post('/content-plans', $this->reviewPayload($brand, [
            'post_types' => ['instagram_carousel', 'instagram_reel'],
            'posts' => [
                'instagram_carousel' => ['media_links' => [self::FILE_A], 'caption' => 'x', 'tags' => [], 'note' => null],
                'instagram_reel' => ['media_links' => [self::FILE_A, self::FILE_B], 'caption' => 'x', 'tags' => [], 'note' => null],
            ],
        ]))->assertSessionHasErrors(['posts.instagram_carousel.media_links', 'posts.instagram_reel.media_links']);

        $this->post('/content-plans', $this->reviewPayload($brand, [
            'post_types' => ['instagram_carousel'],
            'posts' => ['instagram_carousel' => ['media_links' => [self::FILE_A, self::FILE_B], 'caption' => 'x', 'tags' => [], 'note' => null]],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(ContentStatus::InReview, ContentPlan::firstOrFail()->status);
    }

    public function test_media_links_must_be_google_drive_links(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);

        foreach (['https://example.com/a.png', 'https://drive.google.com.evil.example/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz123456/view', 'javascript:alert(1)'] as $bad) {
            $this->actingAs($me)->post('/content-plans', $this->payload($brand, [
                'posts' => ['instagram_feed' => ['media_links' => [$bad], 'caption' => null, 'tags' => [], 'note' => null]],
            ]))->assertSessionHasErrors('posts.instagram_feed.media_links.0');
        }
    }

    public function test_tags_and_caption_limits(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->post('/content-plans', $this->payload($this->brandOf($me), [
            'posts' => ['instagram_feed' => ['media_links' => [], 'caption' => str_repeat('a', 2201), 'tags' => ['bad!tag'], 'note' => null]],
        ]))->assertSessionHasErrors(['posts.instagram_feed.caption', 'posts.instagram_feed.tags.0']);
    }

    // ---- editing ---------------------------------------------------------------------

    public function test_unchecking_a_post_type_removes_its_details(): void
    {
        $me = User::factory()->create();
        $plan = ContentPlan::factory()->create(['content_manager_id' => $me->id, 'brand_id' => $this->brandOf($me)->id]);
        $plan->posts()->createMany([
            ['post_type' => PostType::InstagramFeed, 'media_links' => [], 'tags' => []],
            ['post_type' => PostType::InstagramStory, 'media_links' => [], 'tags' => []],
        ]);

        $this->actingAs($me)->put("/content-plans/{$plan->id}", $this->payload($plan->brand, [
            'post_types' => ['instagram_feed'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['instagram_feed'], $plan->fresh()->postTypeValues());
    }

    public function test_once_past_draft_the_brief_must_stay_complete(): void
    {
        $me = User::factory()->create();
        $plan = ContentPlan::factory()->inProduction()->withPosts()->create(['content_manager_id' => $me->id, 'brand_id' => $this->brandOf($me)->id]);

        $this->actingAs($me)->put("/content-plans/{$plan->id}", $this->payload($plan->brand, ['brief' => '', 'content_pillar' => '']))
            ->assertSessionHasErrors(['brief', 'content_pillar']);
    }

    // ---- who sees what ---------------------------------------------------------------

    public function test_a_draft_is_private_to_its_manager_until_it_is_submitted(): void
    {
        $manager = User::factory()->create();
        $writer = User::factory()->create();
        $plan = ContentPlan::factory()->create([
            'content_manager_id' => $manager->id, 'copywriter_id' => $writer->id,
            'brand_id' => $this->brandOf($manager)->id,
        ]);

        $this->actingAs($writer)->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 0));
        $this->get("/content-plans/{$plan->id}")->assertForbidden();

        $plan->forceFill(['status' => ContentStatus::InProduction])->save(); // status is not mass assignable

        $this->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 1));
        $this->get("/content-plans/{$plan->id}")->assertOk();
    }

    public function test_strangers_are_locked_out_and_superadmin_sees_everything(): void
    {
        $plan = ContentPlan::factory()->inProduction()->create();

        $this->actingAs(User::factory()->create())->get("/content-plans/{$plan->id}")->assertForbidden();
        $this->put("/content-plans/{$plan->id}", [])->assertForbidden();

        $this->actingAs(User::factory()->superadmin()->create())->get("/content-plans/{$plan->id}")->assertOk();
    }

    public function test_assignees_can_open_the_brand_logo_but_never_edit_the_brand(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('brand-logos/l.png', 'png');
        $owner = User::factory()->create();
        $brand = Brand::factory()->create(['created_by' => $owner->id, 'logo_path' => 'brand-logos/l.png']);
        $designer = User::factory()->create();
        $stranger = User::factory()->create();
        ContentPlan::factory()->inProduction()->create(['brand_id' => $brand->id, 'multimedia_creator_id' => $designer->id]);

        $this->actingAs($designer)->get("/brands/{$brand->id}/logo")->assertOk();
        $this->get("/brands/{$brand->id}/edit")->assertForbidden();
        $this->put("/brands/{$brand->id}", ['name' => 'Hacked'])->assertForbidden();

        $this->actingAs($stranger)->get("/brands/{$brand->id}/logo")->assertForbidden();
    }

    // ---- production team -------------------------------------------------------------

    public function test_assignee_fills_post_details_but_cannot_touch_the_brief(): void
    {
        $manager = User::factory()->create();
        $designer = User::factory()->create();
        $brand = $this->brandOf($manager);
        $other = $this->brandOf($manager);
        $plan = ContentPlan::factory()->inProduction()->withPosts([PostType::InstagramFeed])
            ->create(['content_manager_id' => $manager->id, 'multimedia_creator_id' => $designer->id, 'brand_id' => $brand->id, 'title' => 'Original']);

        $this->actingAs($designer)->put("/content-plans/{$plan->id}", [
            'action' => 'save',
            'title' => 'Hacked', 'brand_id' => $other->id, 'post_types' => ['instagram_feed', 'instagram_reel'],
            'posts' => ['instagram_feed' => ['media_links' => [self::FILE_A], 'caption' => 'From the designer', 'tags' => ['x'], 'note' => null]],
        ])->assertSessionHasNoErrors();

        $plan->refresh()->load('posts');
        $this->assertSame('Original', $plan->title);
        $this->assertSame($brand->id, $plan->brand_id);
        $this->assertSame(['instagram_feed'], $plan->postTypeValues());
        $this->assertSame('From the designer', $plan->posts->first()->caption);
        $this->assertSame([self::FILE_A], $plan->posts->first()->media_links);
    }

    public function test_assignee_can_submit_to_review_and_is_then_locked_out_of_editing(): void
    {
        $manager = User::factory()->create();
        $writer = User::factory()->create();
        $plan = ContentPlan::factory()->inProduction()->withPosts([PostType::InstagramFeed])
            ->create(['content_manager_id' => $manager->id, 'copywriter_id' => $writer->id, 'brand_id' => $this->brandOf($manager)->id]);

        $post = ['instagram_feed' => ['media_links' => [self::FILE_A], 'caption' => 'Ready', 'tags' => [], 'note' => null]];

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
        $writer = User::factory()->create();
        $plan = ContentPlan::factory()->inProduction()->withPosts([PostType::InstagramFeed])
            ->create(['content_manager_id' => $manager->id, 'copywriter_id' => $writer->id, 'brand_id' => $this->brandOf($manager)->id]);

        $this->actingAs($writer)->put("/content-plans/{$plan->id}", ['action' => 'review', 'posts' => ['instagram_feed' => ['media_links' => [], 'caption' => '']]])
            ->assertSessionHasErrors(['posts.instagram_feed.media_links', 'posts.instagram_feed.caption']);

        $this->assertSame(ContentStatus::InProduction, $plan->fresh()->status);
    }

    public function test_assignees_cannot_submit_to_production(): void
    {
        $manager = User::factory()->create();
        $writer = User::factory()->create();
        $plan = ContentPlan::factory()->inProduction()->create(['content_manager_id' => $manager->id, 'copywriter_id' => $writer->id, 'brand_id' => $this->brandOf($manager)->id]);

        $this->actingAs($writer)->put("/content-plans/{$plan->id}", ['action' => 'production'])->assertForbidden();
    }

    // ---- workflow --------------------------------------------------------------------

    public function test_manager_can_send_a_plan_in_review_back_to_production(): void
    {
        $manager = User::factory()->create();
        $plan = ContentPlan::factory()->inReview()->withPosts()->create(['content_manager_id' => $manager->id, 'brand_id' => $this->brandOf($manager)->id]);

        $this->actingAs($manager)->put("/content-plans/{$plan->id}", $this->reviewPayload($plan->brand, [
            'action' => 'revise', 'post_types' => ['instagram_feed'],
            'posts' => ['instagram_feed' => ['media_links' => [self::FILE_A], 'caption' => 'Change this', 'tags' => [], 'note' => 'Please redo']],
        ]))->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(ContentStatus::InProduction, $plan->status);
        $this->assertNull($plan->review_submitted_at);
    }

    public function test_buttons_only_work_at_the_right_stage(): void
    {
        $me = User::factory()->create();
        $brand = $this->brandOf($me);
        $inProduction = ContentPlan::factory()->inProduction()->create(['content_manager_id' => $me->id, 'brand_id' => $brand->id]);
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

    // ---- delete ----------------------------------------------------------------------

    public function test_only_the_manager_can_delete_and_only_drafts(): void
    {
        $manager = User::factory()->create();
        $writer = User::factory()->create();
        $brand = $this->brandOf($manager);
        $draft = ContentPlan::factory()->withPosts()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id, 'copywriter_id' => $writer->id]);
        $live = ContentPlan::factory()->inProduction()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id, 'copywriter_id' => $writer->id]);

        $this->actingAs($writer)->delete("/content-plans/{$draft->id}")->assertForbidden();

        $this->actingAs($manager)->delete("/content-plans/{$live->id}")->assertForbidden();
        $this->delete("/content-plans/{$draft->id}")->assertRedirect('/content-plans');

        $this->assertModelMissing($draft);
        $this->assertDatabaseCount('content_plan_posts', 0);
        $this->assertModelExists($live);
    }

    // ---- all content table -----------------------------------------------------------

    public function test_the_table_filters_sorts_and_counts(): void
    {
        $me = User::factory()->create();
        $a = $this->brandOf($me);
        $b = $this->brandOf($me);
        $mine = ContentPlan::factory()->withPosts([PostType::InstagramReel])->create(['content_manager_id' => $me->id, 'brand_id' => $a->id, 'title' => 'Alpha reel', 'publish_at' => '2026-12-05 03:00:00']);
        ContentPlan::factory()->inProduction()->create(['content_manager_id' => $me->id, 'brand_id' => $b->id, 'title' => 'Beta post', 'publish_at' => '2026-12-01 03:00:00']);
        ContentPlan::factory()->inReview()->create(['content_manager_id' => $me->id, 'brand_id' => $b->id, 'title' => 'Gamma', 'publish_at' => null]);
        ContentPlan::factory()->inProduction()->create(['brand_id' => $a->id, 'title' => 'Not visible']);

        $this->actingAs($me);

        $this->get('/content-plans')->assertInertia(fn ($p) => $p
            ->has('plans.data', 3)
            ->where('counts', ['all' => 3, 'draft' => 1, 'in_production' => 1, 'in_review' => 1])
            ->where('plans.data.0.title', 'Alpha reel')   // newest publish date first, no date last
            ->where('plans.data.2.title', 'Gamma'));

        $this->get('/content-plans?sort=publish&dir=asc')->assertInertia(fn ($p) => $p
            ->where('plans.data.0.title', 'Beta post')->where('plans.data.2.title', 'Gamma'));

        $this->get('/content-plans?status=draft')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('counts.all', 3));
        $this->get('/content-plans?brand='.$b->id)->assertInertia(fn ($p) => $p->has('plans.data', 2));
        $this->get('/content-plans?post_type=instagram_reel')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('plans.data.0.id', $mine->id));
        $this->get('/content-plans?search=beta')->assertInertia(fn ($p) => $p->has('plans.data', 1));
        $this->get('/content-plans?from=2026-12-02&to=2026-12-31')->assertInertia(fn ($p) => $p
            ->has('plans.data', 1)->where('plans.data.0.title', 'Alpha reel'));
        $this->get('/content-plans?status=nonsense&sort=hack&dir=sideways&brand[]=1')->assertOk();
    }

    public function test_assigned_to_me_filter(): void
    {
        $me = User::factory()->create();
        $manager = User::factory()->create();
        $brand = $this->brandOf($manager);
        ContentPlan::factory()->inProduction()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id, 'publisher_id' => $me->id]);
        ContentPlan::factory()->inProduction()->create(['content_manager_id' => $manager->id, 'brand_id' => $brand->id]);

        $this->actingAs($manager)->get('/content-plans')->assertInertia(fn ($p) => $p->has('plans.data', 2));
        $this->get('/content-plans?mine=1')->assertInertia(fn ($p) => $p->has('plans.data', 2)); // manager of both

        $this->actingAs($me)->get('/content-plans?mine=1')->assertInertia(fn ($p) => $p->has('plans.data', 1));
    }

    public function test_rows_carry_display_ready_dates_and_overdue_flag(): void
    {
        $me = User::factory()->create();
        ContentPlan::factory()->create([
            'content_manager_id' => $me->id, 'brand_id' => $this->brandOf($me)->id,
            'publish_at' => '2026-12-01 12:00:00', 'deadline_at' => now()->subDay(),
        ]);

        $this->actingAs($me)->get('/content-plans')->assertInertia(fn ($p) => $p
            ->where('plans.data.0.publish_label', 'Tue, 1 Dec 2026 · 19:00')
            ->where('plans.data.0.is_overdue', true)
            ->where('plans.data.0.team.Content Manager.id', $me->id));
    }
}

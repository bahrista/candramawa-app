<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use App\Http\Requests\ContentPlanRequest;
use App\Models\Brand;
use App\Models\ContentPlan;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentPlanController extends Controller
{
    private const SORTS = [
        'publish' => 'publish_at',
        'deadline' => 'deadline_at',
        'title' => 'title',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $base = ContentPlan::query()->visibleTo($user);
        $this->applyFilters($base, $filters, $user);

        // Counts for the "All | Draft | In Production | In Review" links ignore the status filter.
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $column = self::SORTS[$filters['sort']];

        $plans = (clone $base)
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->with([
                'brand',
                'posts:id,content_plan_id,post_type',
                'contentManager:id,name,avatar_path,updated_at',
                'multimediaCreator:id,name,avatar_path,updated_at',
                'copywriter:id,name,avatar_path,updated_at',
                'publisher:id,name,avatar_path,updated_at',
            ])
            ->orderByRaw("($column IS NULL) asc") // empty dates always last
            ->orderBy($column, $filters['dir'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ContentPlan $p) => $this->row($p, $user));

        return Inertia::render('ContentPlans/Index', [
            'plans' => $plans,
            'filters' => $filters,
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect(ContentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all(),
            ],
            'statuses' => collect(ContentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'postTypes' => $this->postTypeOptions(),
            'brands' => Brand::query()
                ->whereIn('id', ContentPlan::query()->visibleTo($user)->select('brand_id'))
                ->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('ContentPlans/Editor', $this->editorProps($request->user(), null));
    }

    public function store(ContentPlanRequest $request): RedirectResponse
    {
        $plan = new ContentPlan;
        $plan->content_manager_id = $request->user()->id;
        $plan->status = ContentStatus::Draft;

        $this->persist($request, $plan);

        $action = $request->planAction();

        return $action === 'save'
            ? redirect()->route('content-plans.show', $plan)->with('success', 'Draft saved.')
            : redirect()->route('content-plans.index')->with('success', $this->submitted($action));
    }

    public function show(Request $request, ContentPlan $contentPlan): Response
    {
        $this->authorize('view', $contentPlan);

        return Inertia::render('ContentPlans/Editor', $this->editorProps($request->user(), $contentPlan));
    }

    public function update(ContentPlanRequest $request, ContentPlan $contentPlan): RedirectResponse
    {
        $this->persist($request, $contentPlan);

        $action = $request->planAction();

        return $action === 'save'
            ? redirect()->route('content-plans.show', $contentPlan)->with('success', 'Changes saved.')
            : redirect()->route('content-plans.index')->with('success', $this->submitted($action));
    }

    public function destroy(ContentPlan $contentPlan): RedirectResponse
    {
        $this->authorize('delete', $contentPlan);

        $title = $contentPlan->title;
        $contentPlan->delete();

        return redirect()->route('content-plans.index')->with('success', "Draft \"{$title}\" deleted.");
    }

    private function persist(ContentPlanRequest $request, ContentPlan $plan): void
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $plan, $data) {
            if ($request->canEditBrief()) {
                $plan->fill(collect($data)->only([
                    'brand_id', 'title', 'content_pillar', 'format', 'brief', 'deadline_mode',
                    'multimedia_creator_id', 'copywriter_id', 'publisher_id',
                ])->all());
                $plan->reference_links = $data['reference_links'] ?? [];
                $plan->publish_at = LocalTime::fromInput($data['publish_at'] ?? null);
                $plan->deadline_at = $data['deadline_mode'] === ContentPlan::DEADLINE_AUTO
                    ? $plan->publish_at?->subHours(config('content.deadline_hours'))
                    : LocalTime::fromInput($data['deadline_at'] ?? null);
            }

            $plan->applyAction($request->planAction());
            $plan->save();

            $types = $request->canEditBrief()
                ? array_values(array_filter(PostType::values(), fn ($v) => in_array($v, $data['post_types'] ?? [], true)))
                : $plan->postTypeValues();

            $plan->posts()->whereNotIn('post_type', $types)->delete();

            foreach ($types as $value) {
                $post = $plan->posts()->firstOrNew(['post_type' => $value]);

                if ($request->canEditPosts()) {
                    $input = $data['posts'][$value] ?? [];
                    $post->media_links = $input['media_links'] ?? [];
                    $post->caption = $input['caption'] ?? null;
                    $post->tags = $input['tags'] ?? [];
                    $post->note = $input['note'] ?? null;
                }

                $post->save();
            }
        });
    }

    private function submitted(string $action): string
    {
        return match ($action) {
            'production' => 'Submitted to production.',
            'review' => 'Submitted to review.',
            'revise' => 'Returned to production.',
            default => 'Saved.',
        };
    }

    // ---- list page -------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $text = fn (string $key) => is_scalar($request->query($key)) ? trim((string) $request->query($key)) : '';

        $sort = $text('sort');
        $dir = $text('dir');

        return [
            'search' => $text('search'),
            'brand' => ctype_digit($text('brand')) ? (int) $text('brand') : null,
            'status' => ContentStatus::tryFrom($text('status'))?->value,
            'post_type' => PostType::tryFrom($text('post_type'))?->value,
            'from' => $this->ymd($text('from')),
            'to' => $this->ymd($text('to')),
            'mine' => $request->boolean('mine'),
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'publish',
            'dir' => $dir === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function ymd(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $value;
    }

    private function applyFilters(BuilderContract $query, array $f, User $user): void
    {
        $query
            ->when($f['search'] !== '', function ($q) use ($f) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['search']).'%';
                $q->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)));
            })
            ->when($f['brand'], fn ($q, $id) => $q->where('brand_id', $id))
            ->when($f['post_type'], fn ($q, $type) => $q->whereHas('posts', fn ($p) => $p->where('post_type', $type)))
            ->when($f['from'], fn ($q, $day) => $q->where('publish_at', '>=', LocalTime::startOfDay($day)))
            ->when($f['to'], fn ($q, $day) => $q->where('publish_at', '<=', LocalTime::endOfDay($day)))
            ->when($f['mine'], fn ($q) => $q->involving($user));
    }

    /** @return array<string, mixed> */
    private function row(ContentPlan $plan, User $user): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'content_pillar' => $plan->content_pillar,
            'format' => $plan->format,
            'status' => $plan->status->value,
            'status_label' => $plan->status->label(),
            'brand' => $this->brandSummary($plan->brand),
            'post_types' => $plan->postTypeValues(),
            'publish_label' => LocalTime::label($plan->publish_at),
            'deadline_label' => LocalTime::label($plan->deadline_at),
            'is_overdue' => $plan->isOverdue(),
            'team' => array_filter([
                'Content Manager' => $this->person($plan->contentManager),
                'Multimedia Creator' => $this->person($plan->multimediaCreator),
                'Copy Writer' => $this->person($plan->copywriter),
                'Publisher' => $this->person($plan->publisher),
            ]),
            'can_delete' => $user->can('delete', $plan),
        ];
    }

    // ---- editor page -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function editorProps(User $user, ?ContentPlan $plan): array
    {
        $brands = $user->isSuperadmin()
            ? Brand::query()->orderBy('name')->get()
            : Brand::query()->where('created_by', $user->id)->orderBy('name')->get();

        // Keep the plan's own brand selectable even if it is not one of "my" brands.
        if ($plan !== null && ! $brands->contains('id', $plan->brand_id)) {
            $brands->push($plan->brand);
        }

        $abilities = $plan === null
            ? ['editBrief' => true, 'editPosts' => true, 'actions' => ['save', 'production', 'review'], 'canDelete' => false]
            : [
                'editBrief' => $user->can('editBrief', $plan),
                'editPosts' => $user->can('editPosts', $plan),
                'actions' => array_values(array_filter(ContentPlan::ACTIONS, fn ($a) => $user->can('perform', [$plan, $a]))),
                'canDelete' => $user->can('delete', $plan),
            ];

        $pillarsByBrand = ContentPlan::query()->visibleTo($user)
            ->whereNotNull('content_pillar')->select('brand_id', 'content_pillar')->distinct()->get()
            ->groupBy('brand_id')->map(fn ($rows) => $rows->pluck('content_pillar')->sort()->values()->all());

        return [
            'plan' => $plan ? $this->present($plan) : null,
            'brands' => $brands->map(fn (Brand $b) => $this->brandSummary($b))->values()->all(),
            'users' => User::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'job_title', 'avatar_path', 'updated_at'])
                ->map(fn (User $u) => $this->person($u) + ['job_title' => $u->job_title])->all(),
            'contentManager' => $this->person($plan?->contentManager ?? $user),
            'postTypes' => $this->postTypeOptions(),
            'abilities' => $abilities,
            'suggestions' => [
                'formats' => config('content.formats'),
                'pillars' => config('content.pillars'),
                'pillarsByBrand' => (object) $pillarsByBrand->all(),
            ],
            'limits' => [
                'caption' => config('content.caption_limit'),
                'tags' => config('content.max_tags'),
                'references' => config('content.max_references'),
            ],
            'timezone' => ['name' => config('content.timezone'), 'abbr' => LocalTime::abbreviation()],
            'deadlineHours' => config('content.deadline_hours'),
        ];
    }

    /** @return array<string, mixed> */
    private function present(ContentPlan $plan): array
    {
        $plan->loadMissing('posts');

        $posts = [];
        foreach ($plan->posts as $post) {
            $posts[$post->post_type->value] = [
                'media_links' => $post->media_links ?? [],
                'caption' => $post->caption ?? '',
                'tags' => $post->tags ?? [],
                'note' => $post->note ?? '',
            ];
        }

        return [
            'id' => $plan->id,
            'brand_id' => $plan->brand_id,
            'title' => $plan->title,
            'content_pillar' => $plan->content_pillar ?? '',
            'format' => $plan->format ?? '',
            'post_types' => $plan->postTypeValues(),
            'publish_at' => LocalTime::toInput($plan->publish_at) ?? '',
            'deadline_mode' => $plan->deadline_mode,
            'deadline_at' => LocalTime::toInput($plan->deadline_at) ?? '',
            'multimedia_creator_id' => $plan->multimedia_creator_id,
            'copywriter_id' => $plan->copywriter_id,
            'publisher_id' => $plan->publisher_id,
            'brief' => $plan->brief ?? '',
            'reference_links' => $plan->reference_links ?? [],
            'status' => $plan->status->value,
            'status_label' => $plan->status->label(),
            'production_started_label' => LocalTime::label($plan->production_started_at),
            'review_submitted_label' => LocalTime::label($plan->review_submitted_at),
            'posts' => (object) $posts,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function postTypeOptions(): array
    {
        return array_map(fn (PostType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'multiple' => $t->multipleMedia(),
            'minMedia' => $t->minMedia(),
            'maxMedia' => $t->maxMedia(),
            'captionRequired' => $t->captionRequired(),
        ], PostType::cases());
    }

    /** @return array<string, mixed>|null */
    private function brandSummary(?Brand $brand): ?array
    {
        return $brand ? ['id' => $brand->id, 'name' => $brand->name, 'color' => $brand->color, 'logo_url' => $brand->logoUrl()] : null;
    }

    /** @return array<string, mixed>|null */
    private function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatarUrl()] : null;
    }
}

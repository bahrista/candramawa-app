<?php

namespace App\Http\Controllers;

use App\Enums\BrandRole;
use App\Enums\ContentStatus;
use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use App\Models\BrandMember;
use App\Models\ContentPlan;
use App\Models\User;
use App\Support\LocalTime;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    private const TABS = ['overview', 'team', 'pillars', 'knowledge', 'products'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $brands = Brand::query()
            ->with('creator:id,name')
            ->visibleTo($user)
            ->when($search !== '', fn ($q) => $q->where(
                'name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'
            ))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Brand $b) => $this->present($b) + [
                'creator' => $b->creator?->name,
                'can_update' => $user->can('update', $b),
            ]);

        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Brands/Create', ['defaultColor' => Brand::DEFAULT_COLOR]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $brand = new Brand($request->safe()->only(['name', 'description', 'color']));
        $brand->created_by = $request->user()->id;

        if ($request->hasFile('logo')) {
            $brand->logo_path = $request->file('logo')->store('brand-logos', 'local');
        }

        DB::transaction(function () use ($brand, $request) {
            $brand->save();

            // The person who creates a brand starts as its Brand Owner; the team can be changed later.
            BrandMember::create(['brand_id' => $brand->id, 'user_id' => $request->user()->id, 'role' => BrandRole::BrandOwner]);
        });

        return redirect()->route('brands.show', ['brand' => $brand, 'tab' => 'team'])
            ->with('success', "Brand {$brand->name} created. Add its team, content pillars and knowledge next.");
    }

    public function show(Request $request, Brand $brand): Response
    {
        $this->authorize('view', $brand);

        $user = $request->user();
        $canUpdate = $user->can('update', $brand);

        $brand->load([
            'members.user',
            'pillars' => fn ($q) => $q->withCount('contentPlans'),
            'knowledgeItems',
            'products',
        ]);

        $team = [];
        foreach (BrandRole::cases() as $role) {
            $team[$role->value] = $brand->members
                ->filter(fn (BrandMember $m) => $m->role === $role && $m->user !== null)
                ->map(fn (BrandMember $m) => Present::person($m->user) + ['is_active' => $m->user->is_active])
                ->values()->all();
        }

        $plans = ContentPlan::query()->visibleTo($user)->where('brand_id', $brand->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Brands/Show', [
            'brand' => $this->present($brand) + [
                'created_by' => $brand->creator?->name,
                'created_label' => LocalTime::label($brand->created_at),
            ],
            'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview',
            'abilities' => [
                'update' => $canUpdate,
                'manageAssets' => $user->can('manageAssets', $brand),
                'createContent' => $user->can('createContent', $brand),
            ],
            'roles' => array_map(fn (BrandRole $r) => ['value' => $r->value, 'label' => $r->label(), 'description' => $r->description()], BrandRole::cases()),
            'team' => $team,
            'users' => $canUpdate
                ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title', 'avatar_path', 'updated_at'])
                    ->map(fn (User $u) => Present::person($u))->all()
                : [],
            'pillars' => $brand->pillars->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'archived' => $p->isArchived(),
                'plans' => $p->content_plans_count,
            ])->values()->all(),
            'knowledge' => $brand->knowledgeItems->map(fn ($k) => [
                'id' => $k->id,
                'title' => $k->title,
                'category' => $k->category,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', $k->content)), 160),
                'links' => count($k->links ?? []),
                'updated_label' => LocalTime::label($k->updated_at),
                'share_url' => $k->shareUrl(),
            ])->values()->all(),
            'products' => $brand->products->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'name' => $p->name,
                'price' => $p->price,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $p->description)), 160),
                'is_active' => $p->is_active,
                'share_url' => $p->shareUrl(),
            ])->values()->all(),
            'stats' => [
                'plans' => (int) $plans->sum(),
                'in_production' => (int) ($plans[ContentStatus::InProduction->value] ?? 0),
                'in_review' => (int) ($plans[ContentStatus::InReview->value] ?? 0),
            ],
        ]);
    }

    public function edit(Brand $brand): Response
    {
        $this->authorize('update', $brand);

        return Inertia::render('Brands/Edit', ['brand' => $this->present($brand)]);
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $brand->fill($request->safe()->only(['name', 'description', 'color']));
        $old = $brand->logo_path;

        if ($request->hasFile('logo')) {
            $brand->logo_path = $request->file('logo')->store('brand-logos', 'local');
        } elseif ($request->boolean('remove_logo')) {
            $brand->logo_path = null;
        }

        $brand->save();

        if ($old && $old !== $brand->logo_path) {
            Storage::disk('local')->delete($old);
        }

        return redirect()->route('brands.show', $brand)->with('success', 'Brand updated.');
    }

    /** Who is on the brand, per role. Only brand managers can change it. */
    public function updateTeam(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $data = $request->validate([
            'team' => ['present', 'array'], // the whole team; an empty one is allowed
            'team.*' => ['nullable', 'array'],
            'team.*.*' => ['integer', 'exists:users,id'],
        ]);

        $activeIds = User::query()->where('is_active', true)->pluck('id')->all();
        $current = $brand->members()->get()->groupBy(fn (BrandMember $m) => $m->role->value);

        $wanted = [];
        foreach (BrandRole::values() as $role) {
            $wanted[$role] = array_values(array_unique(array_map('intval', $data['team'][$role] ?? [])));
            $already = $current->get($role, collect())->pluck('user_id')->all();

            // Existing members may stay even if they were deactivated; new members must be active.
            foreach ($wanted[$role] as $id) {
                if (! in_array($id, $activeIds, true) && ! in_array($id, $already, true)) {
                    throw ValidationException::withMessages(["team.$role" => 'Only active users can be added to the team.']);
                }
            }
        }

        DB::transaction(function () use ($brand, $wanted, $current) {
            foreach ($wanted as $role => $ids) {
                $already = $current->get($role, collect())->pluck('user_id')->all();

                $brand->members()->where('role', $role)->whereNotIn('user_id', $ids)->delete();

                foreach (array_diff($ids, $already) as $id) {
                    BrandMember::create(['brand_id' => $brand->id, 'user_id' => $id, 'role' => $role]);
                }
            }
        });

        return back()->with('success', 'Team updated.');
    }

    /** Logos are private: only people who may view the brand can load its logo. */
    public function logo(Brand $brand): BinaryFileResponse
    {
        $this->authorize('view', $brand);
        abort_unless($brand->logo_path && Storage::disk('local')->exists($brand->logo_path), 404);

        return response()->file(Storage::disk('local')->path($brand->logo_path), [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function present(Brand $brand): array
    {
        return $brand->only(['id', 'name', 'description', 'color']) + ['logo_url' => $brand->logoUrl()];
    }
}

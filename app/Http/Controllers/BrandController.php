<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $brands = Brand::query()
            ->with('creator:id,name')
            ->when(! $user->isSuperadmin(), fn ($q) => $q->where('created_by', $user->id))
            ->when($search !== '', fn ($q) => $q->where(
                'name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'
            ))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Brand $b) => $this->present($b) + ['creator' => $b->creator?->name]);

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

        $brand->save();

        return redirect()->route('brands.index')->with('success', "Brand {$brand->name} created.");
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

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
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

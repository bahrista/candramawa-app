<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLinkLists;
use App\Models\Brand;
use App\Models\BrandProduct;
use App\Support\LocalTime;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The brand's products and services. Each entry has a share link. */
class BrandProductController extends Controller
{
    use HandlesLinkLists;

    public function create(Brand $brand): Response
    {
        $this->authorize('manageAssets', $brand);

        return Inertia::render('Brands/Products/Form', ['brand' => Present::brand($brand), 'item' => null]);
    }

    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $item = new BrandProduct($this->validated($request));
        $item->brand_id = $brand->id;
        $item->created_by = $request->user()->id;
        $item->save();

        return redirect()->route('brands.products.show', [$brand, $item])->with('success', 'Entry added.');
    }

    public function show(Request $request, Brand $brand, BrandProduct $product): Response
    {
        $this->authorize('view', $brand);

        return Inertia::render('Brands/Products/Show', [
            'brand' => Present::brand($brand),
            'item' => $this->present($product),
            'canManage' => $request->user()->can('manageAssets', $brand),
        ]);
    }

    public function edit(Brand $brand, BrandProduct $product): Response
    {
        $this->authorize('manageAssets', $brand);

        return Inertia::render('Brands/Products/Form', ['brand' => Present::brand($brand), 'item' => $this->present($product)]);
    }

    public function update(Request $request, Brand $brand, BrandProduct $product): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $product->update($this->validated($request));

        return redirect()->route('brands.products.show', [$brand, $product])->with('success', 'Entry updated.');
    }

    public function destroy(Brand $brand, BrandProduct $product): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $product->delete();

        return redirect()->route('brands.show', ['brand' => $brand, 'tab' => 'products'])->with('success', 'Entry deleted.');
    }

    private function validated(Request $request): array
    {
        $this->cleanLinks($request);

        return $request->validate([
            'type' => ['required', Rule::in(BrandProduct::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'key_benefits' => ['nullable', 'string', 'max:3000'],
            'price' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ] + $this->linkRules());
    }

    private function present(BrandProduct $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'name' => $item->name,
            'description' => $item->description,
            'key_benefits' => $item->key_benefits,
            'price' => $item->price,
            'is_active' => $item->is_active,
            'links' => $this->presentLinks($item->links),
            'share_url' => $item->shareUrl(),
            'created_by' => $item->creator?->name,
            'updated_label' => LocalTime::label($item->updated_at),
        ];
    }
}

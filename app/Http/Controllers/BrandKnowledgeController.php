<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLinkLists;
use App\Models\Brand;
use App\Models\BrandKnowledgeItem;
use App\Support\LocalTime;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The brand's knowledge base: text the team (and, later, AI) can rely on. Each entry has a share link. */
class BrandKnowledgeController extends Controller
{
    use HandlesLinkLists;

    public function create(Brand $brand): Response
    {
        $this->authorize('manageAssets', $brand);

        return Inertia::render('Brands/Knowledge/Form', [
            'brand' => Present::brand($brand),
            'item' => null,
            'categories' => config('content.knowledge_categories'),
        ]);
    }

    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $item = new BrandKnowledgeItem($this->validated($request));
        $item->brand_id = $brand->id;
        $item->created_by = $request->user()->id;
        $item->save();

        return redirect()->route('brands.knowledge.show', [$brand, $item])->with('success', 'Entry added.');
    }

    public function show(Request $request, Brand $brand, BrandKnowledgeItem $knowledgeItem): Response
    {
        $this->authorize('view', $brand);

        return Inertia::render('Brands/Knowledge/Show', [
            'brand' => Present::brand($brand),
            'item' => $this->present($knowledgeItem),
            'canManage' => $request->user()->can('manageAssets', $brand),
        ]);
    }

    public function edit(Brand $brand, BrandKnowledgeItem $knowledgeItem): Response
    {
        $this->authorize('manageAssets', $brand);

        return Inertia::render('Brands/Knowledge/Form', [
            'brand' => Present::brand($brand),
            'item' => $this->present($knowledgeItem),
            'categories' => config('content.knowledge_categories'),
        ]);
    }

    public function update(Request $request, Brand $brand, BrandKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $knowledgeItem->update($this->validated($request));

        return redirect()->route('brands.knowledge.show', [$brand, $knowledgeItem])->with('success', 'Entry updated.');
    }

    public function destroy(Brand $brand, BrandKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $knowledgeItem->delete();

        return redirect()->route('brands.show', ['brand' => $brand, 'tab' => 'knowledge'])->with('success', 'Entry deleted.');
    }

    private function validated(Request $request): array
    {
        $this->cleanLinks($request);

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:20000'],
        ] + $this->linkRules());
    }

    private function present(BrandKnowledgeItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'category' => $item->category,
            'content' => $item->content,
            'links' => $this->presentLinks($item->links),
            'share_url' => $item->shareUrl(),
            'created_by' => $item->creator?->name,
            'updated_label' => LocalTime::label($item->updated_at),
        ];
    }
}

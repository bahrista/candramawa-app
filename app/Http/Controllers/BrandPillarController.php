<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BrandContentPillar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The brand's content pillars: what the "Content pillar" dropdown of a content plan offers. */
class BrandPillarController extends Controller
{
    public function store(Request $request, Brand $brand): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $data = $request->validate($this->rules($brand));

        $pillar = new BrandContentPillar($data);
        $pillar->brand_id = $brand->id;
        $pillar->save();

        return back()->with('success', "Pillar \"{$pillar->name}\" added.");
    }

    public function update(Request $request, Brand $brand, BrandContentPillar $pillar): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $pillar->update($request->validate($this->rules($brand, $pillar)));

        return back()->with('success', 'Pillar updated.');
    }

    /** Archived pillars stay on old content (for reports) but are no longer offered for new content. */
    public function archive(Brand $brand, BrandContentPillar $pillar): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $pillar->archived_at = $pillar->isArchived() ? null : now();
        $pillar->save();

        return back()->with('success', $pillar->isArchived() ? 'Pillar archived.' : 'Pillar restored.');
    }

    public function destroy(Brand $brand, BrandContentPillar $pillar): RedirectResponse
    {
        $this->authorize('manageAssets', $brand);

        $used = $pillar->contentPlans()->count();

        if ($used > 0) {
            return back()->withErrors(['pillar' => "\"{$pillar->name}\" is used by {$used} content plan".($used === 1 ? '' : 's').'. Archive it instead.']);
        }

        $pillar->delete();

        return back()->with('success', 'Pillar deleted.');
    }

    /** Names are unique within a brand, ignoring upper/lower case. */
    private function rules(Brand $brand, ?BrandContentPillar $pillar = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($brand, $pillar) {
                $taken = BrandContentPillar::query()->where('brand_id', $brand->id)
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                    ->when($pillar, fn ($q) => $q->whereKeyNot($pillar->id))->exists();

                if ($taken) {
                    $fail('This brand already has a pillar with that name.');
                }
            }],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}

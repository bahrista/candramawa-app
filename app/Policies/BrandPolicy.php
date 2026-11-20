<?php

namespace App\Policies;

use App\Enums\BrandRole;
use App\Models\Brand;
use App\Models\ContentPlan;
use App\Models\User;

/**
 * Brand managers: Superadmin, the brand's creator and its Brand Owners.
 *   update        edit the brand details and its team
 *   manageAssets  also Content Managers: content pillars, knowledge base, products and services
 *   createContent same group: plan new content for the brand
 *   view          anyone on the brand's team (any role), or involved in one of its content plans
 */
class BrandPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin()
            || $brand->created_by === $user->id
            || $brand->hasMember($user)
            || ContentPlan::query()->where('brand_id', $brand->id)->visibleTo($user)->exists();
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin()
            || $brand->created_by === $user->id
            || $brand->hasMember($user, BrandRole::BrandOwner);
    }

    public function manageAssets(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand) || $brand->hasMember($user, BrandRole::ContentManager);
    }

    public function createContent(User $user, Brand $brand): bool
    {
        return $this->manageAssets($user, $brand);
    }
}

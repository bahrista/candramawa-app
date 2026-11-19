<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\ContentPlan;
use App\Models\User;

/**
 * A brand is editable by its creator and by any Superadmin. It can also be viewed
 * (name, colour and logo) by anyone who is involved in one of its content plans.
 */
class BrandPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand)
            || ContentPlan::query()->where('brand_id', $brand->id)->visibleTo($user)->exists();
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin() || $brand->created_by === $user->id;
    }
}

<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/** A brand is visible and editable by its creator and by any Superadmin. */
class BrandPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return $user->isSuperadmin() || $brand->created_by === $user->id;
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->view($user, $brand);
    }
}

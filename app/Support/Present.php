<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\User;

/** Small, safe shapes of models for the browser (never whole models: no hashes, no emails). */
final class Present
{
    /** @return array<string, mixed>|null */
    public static function person(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'job_title' => $user->job_title,
            'avatar_url' => $user->avatarUrl(),
        ] : null;
    }

    /** @return array<string, mixed>|null */
    public static function brand(?Brand $brand): ?array
    {
        return $brand ? [
            'id' => $brand->id,
            'name' => $brand->name,
            'color' => $brand->color,
            'logo_url' => $brand->logoUrl(),
        ] : null;
    }
}

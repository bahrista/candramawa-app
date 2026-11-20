<?php

namespace App\Models;

use App\Enums\BrandRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person in one role on one brand (table brand_user). */
class BrandMember extends Model
{
    protected $table = 'brand_user';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => BrandRole::class];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

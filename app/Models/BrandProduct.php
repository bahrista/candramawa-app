<?php

namespace App\Models;

use Database\Factories\BrandProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// created_by is intentionally NOT mass assignable.
#[Fillable(['type', 'name', 'description', 'key_benefits', 'price', 'links', 'is_active'])]
class BrandProduct extends Model
{
    /** @use HasFactory<BrandProductFactory> */
    use HasFactory;

    public const TYPES = ['product', 'service'];

    protected function casts(): array
    {
        return ['links' => 'array', 'is_active' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shareUrl(): string
    {
        return route('brands.products.show', [$this->brand_id, $this->id], false);
    }
}

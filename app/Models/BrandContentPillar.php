<?php

namespace App\Models;

use Database\Factories\BrandContentPillarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class BrandContentPillar extends Model
{
    /** @use HasFactory<BrandContentPillarFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentPlans(): HasMany
    {
        return $this->hasMany(ContentPlan::class, 'content_pillar_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);
        $this->attributes['description'] = $value === '' ? null : $value;
    }
}

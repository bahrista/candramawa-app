<?php

namespace App\Models;

use App\Enums\BrandRole;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// logo_path and created_by are intentionally NOT mass assignable.
#[Fillable(['name', 'description', 'color'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    public const DEFAULT_COLOR = '#2271b1';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BrandMember::class);
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(BrandContentPillar::class)->orderBy('name');
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(BrandKnowledgeItem::class)->orderBy('title');
    }

    public function products(): HasMany
    {
        return $this->hasMany(BrandProduct::class)->orderBy('name');
    }

    public function contentPlans(): HasMany
    {
        return $this->hasMany(ContentPlan::class);
    }

    /** Brands a person belongs to (created, or on the team), or all of them for a Superadmin. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('created_by', $user->id)
            ->orWhereIn('id', BrandMember::query()->select('brand_id')->where('user_id', $user->id)));
    }

    /** Brands a person may plan content for: their own, or where they are Brand Owner / Content Manager. */
    public function scopeCreatableBy(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('created_by', $user->id)
            ->orWhereIn('id', BrandMember::query()->select('brand_id')->where('user_id', $user->id)
                ->whereIn('role', [BrandRole::BrandOwner->value, BrandRole::ContentManager->value])));
    }

    /** @param  BrandRole|list<BrandRole>|null  $roles  null means "any role" */
    public function hasMember(User $user, BrandRole|array|null $roles = null): bool
    {
        $roles = $roles === null ? null : array_map(
            fn (BrandRole $r) => $r->value,
            is_array($roles) ? $roles : [$roles],
        );

        if ($this->relationLoaded('members')) {
            return $this->members->contains(fn (BrandMember $m) => $m->user_id === $user->id
                && ($roles === null || in_array($m->role->value, $roles, true)));
        }

        return $this->members()->where('user_id', $user->id)
            ->when($roles, fn ($q) => $q->whereIn('role', $roles))->exists();
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

    public function setColorAttribute(?string $value): void
    {
        $this->attributes['color'] = $value ? strtolower($value) : self::DEFAULT_COLOR;
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return route('brands.logo', $this).'?v='.($this->updated_at?->timestamp ?? 0);
    }
}

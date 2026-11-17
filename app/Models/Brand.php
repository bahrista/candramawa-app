<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// logo_path and created_by are intentionally NOT mass assignable.
#[Fillable(['name', 'description', 'color'])]
class Brand extends Model
{
    public const DEFAULT_COLOR = '#2271b1';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

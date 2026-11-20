<?php

namespace App\Models;

use Database\Factories\BrandKnowledgeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// created_by is intentionally NOT mass assignable.
#[Fillable(['title', 'category', 'content', 'links'])]
class BrandKnowledgeItem extends Model
{
    /** @use HasFactory<BrandKnowledgeItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['links' => 'array'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The share link (a path, so it keeps working if the domain changes). Opening it needs a login. */
    public function shareUrl(): string
    {
        return route('brands.knowledge.show', [$this->brand_id, $this->id], false);
    }
}

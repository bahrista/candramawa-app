<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['content_type', 'media_links', 'caption', 'tags', 'note'])]
class ContentPlanPost extends Model
{
    protected function casts(): array
    {
        return [
            'media_links' => 'array',
            'tags' => 'array',
        ];
    }

    public function contentPlan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class);
    }
}

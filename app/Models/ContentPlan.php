<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\PostType;
use Database\Factories\ContentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// status, content_manager_id, publish_at, deadline_at and the workflow timestamps are
// intentionally NOT mass assignable; the controller sets them explicitly.
#[Fillable([
    'brand_id', 'title', 'content_pillar', 'format', 'brief', 'reference_links', 'deadline_mode',
    'multimedia_creator_id', 'copywriter_id', 'publisher_id',
])]
class ContentPlan extends Model
{
    /** @use HasFactory<ContentPlanFactory> */
    use HasFactory;

    public const ACTIONS = ['save', 'production', 'review', 'revise'];

    public const DEADLINE_AUTO = 'auto';

    public const DEADLINE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'publish_at' => 'datetime',
            'deadline_at' => 'datetime',
            'reference_links' => 'array',
            'production_started_at' => 'datetime',
            'review_submitted_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'content_manager_id');
    }

    public function multimediaCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'multimedia_creator_id');
    }

    public function copywriter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'copywriter_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPlanPost::class);
    }

    /**
     * Superadmins see everything. Everyone else sees plans they manage, plus plans they
     * are assigned to once those have left the draft stage.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperadmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('content_manager_id', $user->id)
                ->orWhere(function (Builder $q) use ($user) {
                    $q->where('status', '!=', ContentStatus::Draft->value)
                        ->where(fn (Builder $q) => $q->where('multimedia_creator_id', $user->id)
                            ->orWhere('copywriter_id', $user->id)
                            ->orWhere('publisher_id', $user->id));
                });
        });
    }

    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('content_manager_id', $user->id)
            ->orWhere('multimedia_creator_id', $user->id)
            ->orWhere('copywriter_id', $user->id)
            ->orWhere('publisher_id', $user->id));
    }

    public function isManagedBy(User $user): bool
    {
        return $user->isSuperadmin() || $this->content_manager_id === $user->id;
    }

    public function isAssignee(User $user): bool
    {
        return in_array($user->id, [$this->multimedia_creator_id, $this->copywriter_id, $this->publisher_id], true);
    }

    public function isOverdue(): bool
    {
        return $this->deadline_at !== null
            && $this->deadline_at->isPast()
            && in_array($this->status, [ContentStatus::Draft, ContentStatus::InProduction], true);
    }

    /** @return list<string> selected post type values in their canonical order */
    public function postTypeValues(): array
    {
        $have = $this->posts->map(fn (ContentPlanPost $p) => $p->post_type->value)->all();

        return array_values(array_filter(PostType::values(), fn ($v) => in_array($v, $have, true)));
    }

    /** Moves the plan along the workflow. 'save' leaves the status alone. */
    public function applyAction(string $action): void
    {
        switch ($action) {
            case 'production':
                $this->status = ContentStatus::InProduction;
                $this->production_started_at ??= now();
                break;
            case 'review':
                $this->status = ContentStatus::InReview;
                $this->production_started_at ??= now();
                $this->review_submitted_at = now();
                break;
            case 'revise':
                $this->status = ContentStatus::InProduction;
                $this->review_submitted_at = null;
                break;
        }
    }
}

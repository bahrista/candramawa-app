<?php

namespace App\Models;

use App\Enums\BrandRole;
use App\Enums\ContentStatus;
use App\Support\ContentTypes;
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
    'brand_id', 'title', 'content_pillar_id', 'brief', 'brief_references', 'deadline_mode',
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
            'brief_references' => 'array',
            'production_started_at' => 'datetime',
            'review_submitted_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function contentPillar(): BelongsTo
    {
        return $this->belongsTo(BrandContentPillar::class, 'content_pillar_id');
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
     * Superadmins see everything. Everyone else sees the plans they manage, plus (once a plan has
     * left the draft stage) plans they are assigned to and every plan of a brand they belong to.
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
                            ->orWhere('publisher_id', $user->id)
                            ->orWhereIn('brand_id', BrandMember::query()->select('brand_id')->where('user_id', $user->id))
                            ->orWhereIn('brand_id', Brand::query()->select('id')->where('created_by', $user->id)));
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

    /** The people who run this plan: Superadmin and the content manager (the creator). */
    public function isManagedBy(User $user): bool
    {
        return $user->isSuperadmin() || $this->content_manager_id === $user->id;
    }

    /** The brand's creator and Brand Owners may review and manage a plan once it has left draft. */
    public function isReviewedBy(User $user): bool
    {
        if ($this->status === ContentStatus::Draft) {
            return false;
        }

        $brand = $this->brand;

        return $brand->created_by === $user->id || $brand->hasMember($user, BrandRole::BrandOwner);
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

    /** @return list<string> the content types picked for this plan, in the order of config/content_types.php */
    public function contentTypeKeys(): array
    {
        return ContentTypes::order($this->posts->pluck('content_type')->all());
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

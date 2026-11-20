<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\ContentPlan;
use App\Models\User;

/**
 * Draft: only the content manager (the creator) and Superadmin can see and change it.
 * Once submitted, the brand's creator and Brand Owners can review and manage it, assigned
 * people can see it (and fill in the post details while it is in production), and every
 * member of the brand's team can see it.
 */
class ContentPlanPolicy
{
    /** Full control: Superadmin, the content manager, and (after draft) the brand's reviewers. */
    public function manage(User $user, ContentPlan $plan): bool
    {
        return $plan->isManagedBy($user) || $plan->isReviewedBy($user);
    }

    public function view(User $user, ContentPlan $plan): bool
    {
        if ($this->manage($user, $plan)) {
            return true;
        }

        return $plan->status !== ContentStatus::Draft
            && ($plan->isAssignee($user) || $plan->brand->hasMember($user));
    }

    public function editBrief(User $user, ContentPlan $plan): bool
    {
        return $this->manage($user, $plan);
    }

    public function editPosts(User $user, ContentPlan $plan): bool
    {
        return $this->manage($user, $plan)
            || ($plan->isAssignee($user) && $plan->status === ContentStatus::InProduction);
    }

    /** Only drafts can be deleted, and only by their manager. */
    public function delete(User $user, ContentPlan $plan): bool
    {
        return $plan->status === ContentStatus::Draft && $plan->isManagedBy($user);
    }

    /** May this user press this button on this plan right now? */
    public function perform(User $user, ContentPlan $plan, string $action): bool
    {
        if (! $this->view($user, $plan)) {
            return false;
        }

        $manages = $this->manage($user, $plan);

        return match ($action) {
            'save' => $this->editPosts($user, $plan),
            'production' => $manages && $plan->status === ContentStatus::Draft,
            'review' => ($manages && in_array($plan->status, [ContentStatus::Draft, ContentStatus::InProduction], true))
                || ($plan->isAssignee($user) && $plan->status === ContentStatus::InProduction),
            'revise' => $manages && $plan->status === ContentStatus::InReview,
            default => false,
        };
    }
}

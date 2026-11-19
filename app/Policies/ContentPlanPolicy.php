<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\ContentPlan;
use App\Models\User;

/**
 * Draft: only the content manager (and Superadmin) can see and change it.
 * In production: assigned people can also see it and fill in the post details.
 * In review: assigned people can see it; only the content manager (or Superadmin) can change it.
 */
class ContentPlanPolicy
{
    public function view(User $user, ContentPlan $plan): bool
    {
        if ($plan->isManagedBy($user)) {
            return true;
        }

        return $plan->status !== ContentStatus::Draft && $plan->isAssignee($user);
    }

    public function editBrief(User $user, ContentPlan $plan): bool
    {
        return $plan->isManagedBy($user);
    }

    public function editPosts(User $user, ContentPlan $plan): bool
    {
        return $plan->isManagedBy($user)
            || ($plan->isAssignee($user) && $plan->status === ContentStatus::InProduction);
    }

    /** Only drafts can be deleted, and only by their manager. */
    public function delete(User $user, ContentPlan $plan): bool
    {
        return $plan->isManagedBy($user) && $plan->status === ContentStatus::Draft;
    }

    /** May this user press this button on this plan right now? */
    public function perform(User $user, ContentPlan $plan, string $action): bool
    {
        if (! $this->view($user, $plan)) {
            return false;
        }

        $manages = $plan->isManagedBy($user);

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

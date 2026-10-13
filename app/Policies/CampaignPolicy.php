<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageCampaigns();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->canManageCampaigns();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->canManageCampaigns() && $campaign->status === CampaignStatus::Draft;
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }

    public function confirmParticipantList(User $user, Campaign $campaign): bool
    {
        return $user->canManageCampaigns()
            && ! $campaign->isLocked()
            && $campaign->eligibleParticipants()->exists();
    }

    public function openDrawScreen(User $user, Campaign $campaign): bool
    {
        return in_array($campaign->status, [CampaignStatus::Ready, CampaignStatus::InProgress], true)
            && $campaign->winnersRemaining() > 0;
    }

    /**
     * Any active, authenticated user may watch the draw screen once a
     * campaign has left draft — the POST that actually selects a winner is
     * gated separately by the `draw` ability.
     */
    public function viewDrawScreen(User $user, Campaign $campaign): bool
    {
        return $campaign->status !== CampaignStatus::Draft;
    }

    public function draw(User $user, Campaign $campaign): bool
    {
        return $user->canManageCampaigns();
    }

    public function void(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }

    public function cancel(User $user, Campaign $campaign): bool
    {
        return $user->canManageCampaigns();
    }
}

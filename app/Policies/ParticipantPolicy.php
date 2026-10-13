<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

class ParticipantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Participant $participant): bool
    {
        return true;
    }

    public function update(User $user, Participant $participant): bool
    {
        if (! $user->canManageCampaigns()) {
            return false;
        }

        $campaign = $participant->campaign;

        // Once the list is locked and at least one draw exists, participant data is frozen.
        if ($campaign->isLocked() && $campaign->draws()->exists()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Participant $participant): bool
    {
        return $this->update($user, $participant);
    }

    public function export(User $user): bool
    {
        return true;
    }
}

<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Import $import): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageCampaigns();
    }
}

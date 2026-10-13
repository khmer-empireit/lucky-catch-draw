<?php

namespace App\Policies;

use App\Models\Draw;
use App\Models\User;

class DrawPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Draw $draw): bool
    {
        return true;
    }

    public function void(User $user, Draw $draw): bool
    {
        return $user->isSuperAdmin();
    }

    public function print(User $user, Draw $draw): bool
    {
        return true;
    }
}

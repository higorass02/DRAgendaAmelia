<?php

namespace App\Policies;

use App\Models\Professional;
use App\Models\User;

class ProfessionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function view(User $user, Professional $professional): bool
    {
        return $user->hasStaffAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function update(User $user, Professional $professional): bool
    {
        return $user->hasStaffAccess();
    }
}

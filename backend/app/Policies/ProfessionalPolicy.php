<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Professional;
use App\Models\User;

class ProfessionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Staff;
    }

    public function view(User $user, Professional $professional): bool
    {
        return $user->role === UserRole::Staff;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Staff;
    }

    public function update(User $user, Professional $professional): bool
    {
        return $user->role === UserRole::Staff;
    }
}

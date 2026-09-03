<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasStaffAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    // Cobre todas as transições (confirmar, iniciar, concluir, cancelar,
    // não-comparecimento, remarcar) — todas são "mudar o estado da consulta".
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasStaffAccess();
    }
}

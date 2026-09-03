<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    // Escopo atual (Fase 3): só staff gerencia pacientes. Self-service do
    // paciente (ver/cancelar as próprias consultas) é escopo condicional —
    // ver CLAUDE.md, seção 6 — e não tem rota própria ainda.
    public function viewAny(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->hasStaffAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasStaffAccess();
    }
}

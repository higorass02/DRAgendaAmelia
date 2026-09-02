<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Equipe',
            self::Patient => 'Paciente',
        };
    }
}

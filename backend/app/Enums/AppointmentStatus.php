<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'Em Atendimento',
            self::Completed => 'Realizada',
            self::Rescheduled => 'Remarcada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'Não Compareceu',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Rescheduled, self::Cancelled, self::NoShow => true,
            default => false,
        };
    }
}

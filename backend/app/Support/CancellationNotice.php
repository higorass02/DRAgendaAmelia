<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Distinção de cancelamento com/sem antecedência (CLAUDE.md, seção 6).
 * Sem cobrança de multa — só a distinção precisa existir, calculada em cima
 * do momento do cancelamento (StatusHistory.changed_at) vs. o início da
 * consulta, não persistida como coluna própria.
 */
class CancellationNotice
{
    public const DEFAULT_THRESHOLD_HOURS = 24;

    public static function isLastMinute(Carbon $cancelledAt, Carbon $appointmentStart, int $thresholdHours = self::DEFAULT_THRESHOLD_HOURS): bool
    {
        return $cancelledAt->diffInHours($appointmentStart, absolute: true) < $thresholdHours;
    }
}

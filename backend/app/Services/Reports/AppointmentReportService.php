<?php

namespace App\Services\Reports;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\StatusHistory;
use App\Support\CancellationNotice;
use Carbon\Carbon;

/**
 * Read-model sobre o domínio (CLAUDE.md, seção 9) — reaproveita a auditoria
 * (StatusHistory) e os status já existentes, não introduz nenhuma tabela
 * nova. Métricas decididas na Fase 1: no-show, cancelamento por
 * antecedência, remarcação, ocupação por profissional.
 */
class AppointmentReportService
{
    /**
     * Status que contam como "ocupando" o horário do profissional pra fins
     * de ocupação — inclui no-show (o profissional ficou reservado/esperando
     * mesmo sem o paciente aparecer). Exclui cancelled/rescheduled (o
     * horário, no fim, não foi de fato usado por essa consulta).
     */
    private const OCCUPYING_STATUSES = [
        AppointmentStatus::Scheduled,
        AppointmentStatus::Confirmed,
        AppointmentStatus::InProgress,
        AppointmentStatus::Completed,
        AppointmentStatus::NoShow,
    ];

    public function build(Carbon $from, Carbon $to, array $professionalIds = [], array $patientIds = []): array
    {
        $appointments = Appointment::query()
            ->whereBetween('start_at', [$from, $to])
            ->when($professionalIds, fn ($q) => $q->whereIn('professional_id', $professionalIds))
            ->when($patientIds, fn ($q) => $q->whereIn('patient_id', $patientIds))
            ->get(['id', 'professional_id', 'status', 'start_at', 'end_at']);

        $total = $appointments->count();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => ['professional_id' => $professionalIds, 'patient_id' => $patientIds],
            'no_show_rate' => $this->rate($appointments->where('status', AppointmentStatus::NoShow)->count(), $total),
            'reschedule_rate' => $this->rate($appointments->where('status', AppointmentStatus::Rescheduled)->count(), $total),
            'cancellations' => $this->cancellations($from, $to, $professionalIds, $patientIds),
            'occupancy_by_professional' => $this->occupancyByProfessional($appointments, $from, $to, $professionalIds),
        ];
    }

    private function cancellations(Carbon $from, Carbon $to, array $professionalIds, array $patientIds): array
    {
        $cancellations = StatusHistory::query()
            ->join('appointments', 'appointments.id', '=', 'status_histories.appointment_id')
            ->where('status_histories.to_status', AppointmentStatus::Cancelled)
            ->whereBetween('appointments.start_at', [$from, $to])
            ->when($professionalIds, fn ($q) => $q->whereIn('appointments.professional_id', $professionalIds))
            ->when($patientIds, fn ($q) => $q->whereIn('appointments.patient_id', $patientIds))
            ->get(['status_histories.changed_at', 'appointments.start_at']);

        $lastMinute = $cancellations->filter(
            fn ($row) => CancellationNotice::isLastMinute(Carbon::parse($row->changed_at), Carbon::parse($row->start_at))
        )->count();

        $total = $cancellations->count();

        return [
            'total' => $total,
            'last_minute' => $lastMinute,
            'with_notice' => $total - $lastMinute,
            'last_minute_rate' => $this->rate($lastMinute, $total),
        ];
    }

    private function occupancyByProfessional($appointments, Carbon $from, Carbon $to, array $professionalIds): array
    {
        $weeks = max(1, $from->diffInDays($to) / 7);

        $occupiedByProfessional = $appointments
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->groupBy('professional_id')
            ->map(fn ($group) => $group->sum(fn ($a) => $a->start_at->diffInMinutes($a->end_at)));

        return Professional::query()
            ->with('availabilities')
            ->when($professionalIds, fn ($q) => $q->whereIn('id', $professionalIds))
            ->get()
            ->map(function (Professional $professional) use ($occupiedByProfessional, $weeks) {
                $weeklyCapacity = $professional->availabilities->sum(
                    fn ($a) => Carbon::parse($a->start_time)->diffInMinutes(Carbon::parse($a->end_time))
                );
                $capacity = (int) round($weeklyCapacity * $weeks);
                $occupied = (int) ($occupiedByProfessional[$professional->id] ?? 0);

                return [
                    'professional_id' => $professional->id,
                    'name' => $professional->name,
                    'occupied_minutes' => $occupied,
                    'capacity_minutes' => $capacity,
                    'occupancy_rate' => $this->rate($occupied, $capacity),
                ];
            })
            ->values()
            ->all();
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}

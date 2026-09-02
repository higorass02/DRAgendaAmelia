<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    public static function validTransitions(): array
    {
        return [
            'scheduled -> confirmed' => [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed],
            'scheduled -> cancelled' => [AppointmentStatus::Scheduled, AppointmentStatus::Cancelled],
            'confirmed -> in_progress' => [AppointmentStatus::Confirmed, AppointmentStatus::InProgress],
            'confirmed -> rescheduled' => [AppointmentStatus::Confirmed, AppointmentStatus::Rescheduled],
            'confirmed -> cancelled' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled],
            'confirmed -> no_show' => [AppointmentStatus::Confirmed, AppointmentStatus::NoShow],
            'in_progress -> completed' => [AppointmentStatus::InProgress, AppointmentStatus::Completed],
        ];
    }

    #[DataProvider('validTransitions')]
    public function test_allows_valid_transition(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    public static function invalidTransitions(): array
    {
        return [
            'scheduled -> in_progress (pula confirmed)' => [AppointmentStatus::Scheduled, AppointmentStatus::InProgress],
            'scheduled -> completed' => [AppointmentStatus::Scheduled, AppointmentStatus::Completed],
            'scheduled -> no_show' => [AppointmentStatus::Scheduled, AppointmentStatus::NoShow],
            'confirmed -> scheduled (nao volta)' => [AppointmentStatus::Confirmed, AppointmentStatus::Scheduled],
            'in_progress -> cancelled' => [AppointmentStatus::InProgress, AppointmentStatus::Cancelled],
            'in_progress -> confirmed (nao volta)' => [AppointmentStatus::InProgress, AppointmentStatus::Confirmed],
            'completed -> scheduled' => [AppointmentStatus::Completed, AppointmentStatus::Scheduled],
            'cancelled -> scheduled' => [AppointmentStatus::Cancelled, AppointmentStatus::Scheduled],
            'no_show -> scheduled' => [AppointmentStatus::NoShow, AppointmentStatus::Scheduled],
            'rescheduled -> scheduled' => [AppointmentStatus::Rescheduled, AppointmentStatus::Scheduled],
        ];
    }

    #[DataProvider('invalidTransitions')]
    public function test_rejects_invalid_transition(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public static function terminalStatuses(): array
    {
        return [
            'completed' => [AppointmentStatus::Completed, true],
            'rescheduled' => [AppointmentStatus::Rescheduled, true],
            'cancelled' => [AppointmentStatus::Cancelled, true],
            'no_show' => [AppointmentStatus::NoShow, true],
            'scheduled' => [AppointmentStatus::Scheduled, false],
            'confirmed' => [AppointmentStatus::Confirmed, false],
            'in_progress' => [AppointmentStatus::InProgress, false],
        ];
    }

    #[DataProvider('terminalStatuses')]
    public function test_isTerminal_matches_expectation(AppointmentStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isTerminal());
    }

    public function test_terminal_status_never_allows_any_transition(): void
    {
        foreach (AppointmentStatus::cases() as $terminal) {
            if (! $terminal->isTerminal()) {
                continue;
            }

            foreach (AppointmentStatus::cases() as $target) {
                $this->assertFalse(
                    $terminal->canTransitionTo($target),
                    "{$terminal->value} não deveria poder ir para {$target->value} (estado terminal)."
                );
            }
        }
    }
}

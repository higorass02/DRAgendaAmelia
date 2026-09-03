<?php

namespace Tests\Feature\Reports;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\StatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentReportsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $staff): array
    {
        return ['Authorization' => 'Bearer '.$staff->createToken('api')->plainTextToken];
    }

    public function test_guest_cannot_view_reports(): void
    {
        $this->getJson('/api/v1/reports')->assertUnauthorized();
    }

    public function test_computes_all_four_reports_for_the_given_period(): void
    {
        $staff = User::factory()->create();

        // Segunda a sexta, 08h-18h => 600min/dia * 5 dias = 3000 min/semana de capacidade.
        $professional = Professional::factory()->create();
        foreach (range(1, 5) as $weekday) {
            $professional->availabilities()->create([
                'weekday' => $weekday,
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        $monday = Carbon::parse('next monday')->startOfDay();
        $from = $monday->toDateString();
        $to = $monday->copy()->addDays(6)->toDateString();

        $slot = fn (int $day, string $time) => $monday->copy()->addDays($day)->setTimeFromTimeString($time);

        // 2 consultas "ocupando" a agenda (confirmed), 60min cada.
        foreach ([1, 2] as $i) {
            Appointment::factory()->create([
                'professional_id' => $professional->id,
                'status' => AppointmentStatus::Confirmed,
                'start_at' => $slot($i, '09:00:00'),
                'end_at' => $slot($i, '10:00:00'),
            ]);
        }

        // 1 não-comparecimento — ocupou o horário (60min) mas não é "sucesso".
        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::NoShow,
            'start_at' => $slot(1, '11:00:00'),
            'end_at' => $slot(1, '12:00:00'),
        ]);

        // 1 remarcada.
        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Rescheduled,
            'start_at' => $slot(2, '13:00:00'),
            'end_at' => $slot(2, '13:30:00'),
        ]);

        // 1 cancelamento de última hora (< 24h de antecedência).
        $lastMinute = Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Cancelled,
            'start_at' => $slot(3, '10:00:00'),
            'end_at' => $slot(3, '10:30:00'),
        ]);
        StatusHistory::factory()->create([
            'appointment_id' => $lastMinute->id,
            'from_status' => AppointmentStatus::Scheduled,
            'to_status' => AppointmentStatus::Cancelled,
            'changed_by' => $staff->id,
            'changed_at' => $slot(3, '10:00:00')->copy()->subHours(2),
        ]);

        // 1 cancelamento com antecedência (>= 24h).
        $withNotice = Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Cancelled,
            'start_at' => $slot(4, '10:00:00'),
            'end_at' => $slot(4, '10:30:00'),
        ]);
        StatusHistory::factory()->create([
            'appointment_id' => $withNotice->id,
            'from_status' => AppointmentStatus::Scheduled,
            'to_status' => AppointmentStatus::Cancelled,
            'changed_by' => $staff->id,
            'changed_at' => $slot(4, '10:00:00')->copy()->subHours(48),
        ]);

        $response = $this->withHeaders($this->headers($staff))
            ->getJson("/api/v1/reports?from={$from}&to={$to}");

        $response->assertOk();
        $response->assertJsonPath('no_show_rate', 16.67);
        $response->assertJsonPath('reschedule_rate', 16.67);
        $response->assertJsonPath('cancellations.total', 2);
        $response->assertJsonPath('cancellations.last_minute', 1);
        $response->assertJsonPath('cancellations.with_notice', 1);
        $this->assertEquals(50.0, $response->json('cancellations.last_minute_rate'));

        $occupancy = $response->json('occupancy_by_professional');
        $this->assertCount(1, $occupancy);
        $this->assertSame($professional->id, $occupancy[0]['professional_id']);
        $this->assertSame(180, $occupancy[0]['occupied_minutes']);
        $this->assertSame(3000, $occupancy[0]['capacity_minutes']);
        $this->assertEquals(6.0, $occupancy[0]['occupancy_rate']);
    }

    public function test_returns_zeroes_when_there_is_no_data_in_period(): void
    {
        $staff = User::factory()->create();

        $response = $this->withHeaders($this->headers($staff))
            ->getJson('/api/v1/reports?from=2020-01-01&to=2020-01-07');

        $response->assertOk();
        $this->assertEquals(0.0, $response->json('no_show_rate'));
        $this->assertEquals(0.0, $response->json('reschedule_rate'));
        $response->assertJsonPath('cancellations.total', 0);
    }

    public function test_can_filter_by_professional(): void
    {
        $staff = User::factory()->create();
        $professionalA = Professional::factory()->create();
        $professionalB = Professional::factory()->create();

        Appointment::factory()->create(['professional_id' => $professionalA->id, 'status' => AppointmentStatus::NoShow]);
        Appointment::factory()->create(['professional_id' => $professionalB->id, 'status' => AppointmentStatus::NoShow]);
        Appointment::factory()->create(['professional_id' => $professionalB->id, 'status' => AppointmentStatus::Confirmed]);

        $from = now()->toDateString();
        $to = now()->addDays(60)->toDateString();

        $response = $this->withHeaders($this->headers($staff))
            ->getJson("/api/v1/reports?professional_id={$professionalB->id}&from={$from}&to={$to}");

        $response->assertOk();
        // 1 no_show de 2 consultas do profissional B = 50% (ignora o do A).
        $this->assertEquals(50.0, $response->json('no_show_rate'));

        $occupancy = collect($response->json('occupancy_by_professional'));
        $this->assertCount(1, $occupancy);
        $this->assertSame($professionalB->id, $occupancy->first()['professional_id']);
    }

    public function test_can_filter_by_patient(): void
    {
        $staff = User::factory()->create();
        $patientA = \App\Models\Patient::factory()->create();
        $patientB = \App\Models\Patient::factory()->create();

        Appointment::factory()->create(['patient_id' => $patientA->id, 'status' => AppointmentStatus::NoShow]);
        Appointment::factory()->create(['patient_id' => $patientB->id, 'status' => AppointmentStatus::Confirmed]);

        $from = now()->toDateString();
        $to = now()->addDays(60)->toDateString();

        $response = $this->withHeaders($this->headers($staff))
            ->getJson("/api/v1/reports?patient_id={$patientA->id}&from={$from}&to={$to}");

        $response->assertOk();
        $this->assertEquals(100.0, $response->json('no_show_rate'));
    }
}

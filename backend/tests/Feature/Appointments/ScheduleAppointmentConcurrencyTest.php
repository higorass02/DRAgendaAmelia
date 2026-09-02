<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Teste de concorrência real: duas conexões MySQL distintas disputando o
 * mesmo horário. Não usa RefreshDatabase de propósito — esse trait isola os
 * dados numa transação nunca commitada, invisível para o processo filho que
 * abre sua própria conexão. Aqui os dados são commitados de verdade e
 * limpos manualmente no tearDown.
 */
class ScheduleAppointmentConcurrencyTest extends TestCase
{
    private ?Professional $professional = null;

    private ?Patient $patientA = null;

    private ?Patient $patientB = null;

    private ?User $staff = null;

    protected function tearDown(): void
    {
        Appointment::query()->where('professional_id', $this->professional?->id)->delete();
        $this->patientA?->delete();
        $this->patientB?->delete();
        $this->professional?->delete();
        $this->staff?->delete();

        parent::tearDown();
    }

    public function test_only_one_of_two_concurrent_bookings_for_the_same_slot_succeeds(): void
    {
        $this->professional = Professional::factory()->create();
        $this->professional->availabilities()->create([
            'weekday' => now()->addDay()->dayOfWeek,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ]);
        $this->patientA = Patient::factory()->create();
        $this->patientB = Patient::factory()->create();
        $this->staff = User::factory()->create();

        $startAt = now()->addDay()->setTime(10, 0, 0)->toDateTimeString();
        $endAt = now()->addDay()->setTime(10, 30, 0)->toDateTimeString();

        // Conexão A (este processo): abre transação e SEGURA o lock/insert,
        // sem commitar ainda — simula a primeira requisição em andamento.
        DB::beginTransaction();

        $lock = DB::table('appointments')
            ->where('professional_id', $this->professional->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->lockForUpdate()
            ->exists();
        $this->assertFalse($lock, 'não deveria haver conflito ainda');

        $appointmentAId = DB::table('appointments')->insertGetId([
            'patient_id' => $this->patientA->id,
            'professional_id' => $this->professional->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'scheduled',
            'created_by' => $this->staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Conexão B (processo filho de verdade): tenta reservar o MESMO
        // horário enquanto A ainda não commitou. Deve bloquear no
        // SELECT ... FOR UPDATE e, ao desbloquear, encontrar o conflito.
        $script = $this->concurrentBookingScript(
            patientId: $this->patientB->id,
            professionalId: $this->professional->id,
            staffId: $this->staff->id,
            startAt: $startAt,
            endAt: $endAt,
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            ['php', 'artisan', 'tinker', "--execute={$script}"],
            $descriptors,
            $pipes,
            base_path(),
            null,
        );
        $this->assertIsResource($process);

        // Dá tempo do processo filho subir o Laravel e chegar no SELECT ...
        // FOR UPDATE (que vai bloquear, preso no lock da conexão A).
        usleep(600_000);

        // Libera o lock de A — só agora B consegue prosseguir e enxergar o
        // appointment que A acabou de commitar.
        DB::commit();

        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertStringContainsString(
            'CONFLICT',
            $output,
            "esperava CONFLICT no output do processo B. stdout={$output} stderr={$errorOutput} exit={$exitCode}"
        );

        $occupying = Appointment::where('professional_id', $this->professional->id)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->count();

        $this->assertSame(1, $occupying, 'só uma das duas reservas concorrentes deveria ter vingado');
    }

    private function concurrentBookingScript(int $patientId, int $professionalId, int $staffId, string $startAt, string $endAt): string
    {
        return <<<PHP
        try {
            \$patient = \App\Models\Patient::find({$patientId});
            \$professional = \App\Models\Professional::find({$professionalId});
            \$staff = \App\Models\User::find({$staffId});
            (new \App\Actions\Appointments\ScheduleAppointment)->handle(
                patient: \$patient,
                professional: \$professional,
                startAt: \Carbon\Carbon::parse('{$startAt}'),
                endAt: \Carbon\Carbon::parse('{$endAt}'),
                actor: \$staff,
            );
            echo 'SUCCESS';
        } catch (\App\Exceptions\ScheduleConflictException \$e) {
            echo 'CONFLICT';
        } catch (\Throwable \$e) {
            echo 'ERROR:' . get_class(\$e) . ':' . \$e->getMessage();
        }
        PHP;
    }
}

<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $staff = User::factory()->create([
            'name' => 'Recepção Central',
            'email' => 'staff@dragenda.test',
        ]);

        $professionals = Professional::factory(5)->create();

        foreach ($professionals as $professional) {
            // Segunda a sexta, 08h-18h.
            foreach (range(1, 5) as $weekday) {
                $professional->availabilities()->create([
                    'weekday' => $weekday,
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                ]);
            }
        }

        $patients = Patient::factory(20)->create();

        // Um paciente com login de self-service, pra demonstrar o hook de schema.
        $patientUser = User::factory()->create([
            'name' => 'Paciente Demo',
            'email' => 'patient@dragenda.test',
        ]);
        $patientUser->forceFill(['role' => UserRole::Patient])->save();
        $patients->first()->update(['user_id' => $patientUser->id]);

        $statusDistribution = [
            AppointmentStatus::Scheduled,
            AppointmentStatus::Scheduled,
            AppointmentStatus::Confirmed,
            AppointmentStatus::Confirmed,
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ];

        foreach (range(1, 15) as $i) {
            $finalStatus = $statusDistribution[array_rand($statusDistribution)];

            $appointment = Appointment::factory()->create([
                'patient_id' => $patients->random()->id,
                'professional_id' => $professionals->random()->id,
                'status' => $finalStatus,
                'created_by' => $staff->id,
            ]);

            StatusHistory::factory()->create([
                'appointment_id' => $appointment->id,
                'from_status' => null,
                'to_status' => AppointmentStatus::Scheduled,
                'changed_by' => $staff->id,
                'changed_at' => $appointment->created_at,
            ]);

            if ($finalStatus !== AppointmentStatus::Scheduled) {
                StatusHistory::factory()->create([
                    'appointment_id' => $appointment->id,
                    'from_status' => AppointmentStatus::Scheduled,
                    'to_status' => $finalStatus,
                    'changed_by' => $staff->id,
                    'changed_at' => $appointment->created_at->addHour(),
                ]);
            }
        }
    }
}

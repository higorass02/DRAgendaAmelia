<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('professional_id')->constrained()->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            // Enum interno (App\Enums\AppointmentStatus) guardado como string —
            // evita ALTER TABLE toda vez que um estado for ajustado.
            $table->string('status');
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Índice de apoio para consulta de agenda por profissional/período.
            // O índice único anti-overbooking (rede final) entra na Fase 2,
            // junto da estratégia de lock/concorrência.
            $table->index(['professional_id', 'start_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

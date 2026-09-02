<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rede final anti-overbooking a nível de banco (CLAUDE.md, seção 3).
     *
     * MySQL não tem índice único parcial/condicional. Contorno: uma coluna
     * gerada que só carrega start_at quando o status "ocupa" a agenda
     * (scheduled/confirmed) — NULL nos demais. MySQL permite múltiplos NULL
     * num índice único, então status terminais nunca colidem entre si nem
     * bloqueiam um novo agendamento no mesmo horário depois de cancelado.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
            ADD COLUMN occupied_start_at DATETIME
            GENERATED ALWAYS AS (
                CASE WHEN status IN ('scheduled', 'confirmed') THEN start_at ELSE NULL END
            ) STORED
        SQL);

        Schema::table('appointments', function ($table) {
            $table->unique(['professional_id', 'occupied_start_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function ($table) {
            $table->dropUnique(['professional_id', 'occupied_start_at']);
        });

        DB::statement('ALTER TABLE appointments DROP COLUMN occupied_start_at');
    }
};

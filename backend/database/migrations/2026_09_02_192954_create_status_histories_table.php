<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            // Nulo na primeira transição (criação da consulta) — não há "de".
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('changed_at');

            $table->index(['appointment_id', 'changed_at']);
            $table->index('to_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};

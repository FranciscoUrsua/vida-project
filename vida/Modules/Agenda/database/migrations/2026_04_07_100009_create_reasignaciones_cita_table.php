<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reasignaciones_cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();
            $table->foreignId('slot_original_id')->constrained('slots')->cascadeOnDelete();
            $table->foreignId('slot_nuevo_id')->constrained('slots')->cascadeOnDelete();
            $table->foreignId('profesional_original_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profesional_nuevo_id')->constrained('users')->cascadeOnDelete();
            $table->string('motivo');
            $table->text('notas')->nullable();
            $table->foreignId('realizada_por_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('cita_id');
            $table->index('slot_original_id');
            $table->index('profesional_original_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reasignaciones_cita');
    }
};

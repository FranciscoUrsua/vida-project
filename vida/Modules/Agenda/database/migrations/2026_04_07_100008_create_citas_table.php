<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->unique()->constrained('slots')->cascadeOnDelete();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->cascadeOnDelete();
            $table->foreignId('profesional_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tipo_slot_id')->constrained('tipos_slot')->cascadeOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado')->default('confirmada');
            $table->text('motivo')->nullable();
            $table->string('origen')->default('interno');
            $table->string('referencia_externa')->nullable();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamp('completada_en')->nullable();
            $table->text('notas_profesional')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('ciudadano_id');
            $table->index('profesional_id');
            $table->index(['profesional_id', 'fecha']);
            $table->index(['centro_id', 'fecha']);
            $table->index('estado');
            $table->index('referencia_externa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};

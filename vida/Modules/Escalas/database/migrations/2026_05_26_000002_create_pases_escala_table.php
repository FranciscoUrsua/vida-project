<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pases_escala', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_escala_id')->constrained('tipo_escalas');
            $table->foreignId('historia_id')->constrained('historias_sociales');
            $table->foreignId('profesional_id')->constrained('users');
            $table->date('fecha');
            $table->jsonb('respuestas')->default('{}');
            $table->integer('score_total')->nullable();
            $table->jsonb('scores_seccion')->nullable();
            $table->string('interpretacion_codigo', 50)->nullable();
            $table->text('notas')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->foreignId('ficha_id')->nullable()->constrained('fichas');
            $table->foreignId('entrevista_id')->nullable()->constrained('entrevistas');
            $table->timestamps();

            $table->index(['historia_id', 'tipo_escala_id', 'fecha']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pases_escala');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de objetivos (backoffice)
        Schema::create('objetivos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_plan_id')->constrained('tipos_plan')->cascadeOnDelete();
            $table->enum('nivel', ['general', 'especifico']);
            $table->foreignId('objetivo_general_id')
                  ->nullable()
                  ->constrained('objetivos_catalogo')
                  ->nullOnDelete();
            $table->text('texto');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['tipo_plan_id', 'nivel', 'activo']);
        });

        // Objetivos reales del plan
        Schema::create('plan_objetivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('objetivo_catalogo_id')
                  ->nullable()
                  ->constrained('objetivos_catalogo')
                  ->nullOnDelete();
            $table->enum('nivel', ['general', 'especifico']);
            $table->foreignId('objetivo_general_id')
                  ->nullable()
                  ->constrained('plan_objetivos')
                  ->nullOnDelete();
            $table->text('texto');
            $table->enum('estado', ['pendiente', 'en_proceso', 'conseguido', 'abandonado'])
                  ->default('pendiente');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'nivel']);
        });

        // Actuaciones del Ayuntamiento
        Schema::create('plan_actuaciones_ayuntamiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('prestacion_id')->constrained('prestaciones'); // FK obligatoria
            $table->text('descripcion_especifica')->nullable();
            $table->foreignId('responsable_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->enum('estado', ['pendiente', 'en_curso', 'completada', 'cancelada'])
                  ->default('pendiente');
            $table->date('fecha_inicio_prevista')->nullable();
            $table->date('fecha_fin_prevista')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('plan_id');
        });

        // Actuaciones del ciudadano
        Schema::create('plan_actuaciones_ciudadano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->text('descripcion');
            $table->foreignId('prestacion_id')
                  ->nullable()
                  ->constrained('prestaciones')
                  ->nullOnDelete();
            $table->enum('estado', ['pendiente', 'en_curso', 'completada', 'cancelada'])
                  ->default('pendiente');
            $table->date('fecha_inicio_prevista')->nullable();
            $table->date('fecha_fin_prevista')->nullable();
            $table->date('fecha_fin_real')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('plan_id');
        });

        // Participantes del plan
        Schema::create('plan_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('rol_en_plan');
            $table->foreignId('servicio_id')
                  ->nullable()
                  ->constrained('servicios')
                  ->nullOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'user_id']);
        });

        // Historial de cambios
        Schema::create('plan_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes_intervencion')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('profesional_id')->constrained('users');
            $table->enum('origen', ['discrecional', 'seguimiento']);
            $table->foreignId('seguimiento_id')
                  ->nullable()
                  ->constrained('seguimientos_plan')
                  ->nullOnDelete();
            $table->text('motivo');
            $table->json('snapshot');
            $table->timestamp('created_at');

            $table->index(['plan_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cambios');
        Schema::dropIfExists('plan_participantes');
        Schema::dropIfExists('plan_actuaciones_ciudadano');
        Schema::dropIfExists('plan_actuaciones_ayuntamiento');
        Schema::dropIfExists('plan_objetivos');
        Schema::dropIfExists('objetivos_catalogo');
    }
};

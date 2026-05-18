<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes_intervencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historia_id')
                ->constrained('historias_sociales')
                ->onDelete('restrict');
            $table->string('tipo', 30)->comment('general_asp, especializado');
            $table->unsignedBigInteger('servicio_especializado_id')->nullable();
            $table->foreignId('profesional_responsable_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->unsignedBigInteger('plan_asp_id')->nullable()
                ->comment('Para planes especializados: referencia al plan general ASP de origen');
            $table->string('estado', 30)->default('borrador')->comment('borrador, activo, en_revision, cerrado');
            $table->date('fecha_inicio');
            $table->date('fecha_firma')->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->string('motivo_cierre', 30)->nullable();
            $table->text('objetivos')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('historia_id');
            $table->index('plan_asp_id');
        });

        // FK auto-referencial añadida después de crear la tabla
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->foreign('plan_asp_id')
                ->references('id')
                ->on('planes_intervencion')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_intervencion');
    }
};

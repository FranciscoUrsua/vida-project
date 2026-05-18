<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisiones_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('planes_intervencion')
                ->onDelete('restrict');
            $table->unsignedInteger('version_anterior');
            $table->unsignedInteger('version_nueva');
            $table->foreignId('profesional_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->date('fecha');
            $table->text('motivo_revision');
            $table->foreignId('seguimiento_id')
                ->nullable()
                ->constrained('seguimientos_plan')
                ->onDelete('set null');
            $table->timestamps();

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_plan');
    }
};

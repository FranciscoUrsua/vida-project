<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_apuntes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('planes_intervencion')
                ->onDelete('restrict');
            $table->foreignId('autor_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->date('fecha');
            $table->string('tipo', 30)->comment('entrevista, documento, derivacion, seguimiento, anotacion');
            $table->string('apuntable_type', 100)->nullable();
            $table->unsignedBigInteger('apuntable_id')->nullable();
            $table->text('contenido')->nullable();
            $table->string('visibilidad', 20)->default('profesionales')
                ->comment('privada, profesionales, ciudadano');
            $table->timestamps();

            $table->index('plan_id');
            $table->index('autor_id');
            $table->index(['apuntable_type', 'apuntable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_apuntes');
    }
};

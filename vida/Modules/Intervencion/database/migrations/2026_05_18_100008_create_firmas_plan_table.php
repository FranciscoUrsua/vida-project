<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained('planes_intervencion')
                ->onDelete('restrict');
            $table->unsignedInteger('version')->comment('Versión del plan a la que corresponde esta firma');
            $table->text('firma_ciudadano')->nullable()->comment('Blob o referencia al archivo de firma del ciudadano');
            $table->text('firma_profesional')->nullable()->comment('Blob o referencia al archivo de firma del profesional');
            $table->string('metodo_firma', 40)->nullable()->comment('wacom, manuscrita_escaneada, digital_certificada');
            $table->date('fecha_firma')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'version']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas_plan');
    }
};

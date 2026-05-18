<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sia_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->onDelete('restrict');
            $table->foreignId('auxiliar_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->dateTime('fecha_hora');
            $table->string('canal', 30)->comment('presencial, telefonico, digital');
            $table->text('descripcion_demanda');
            $table->string('clasificacion', 40)->comment('competencia_municipal, otra_administracion, informacion_general');
            $table->text('informacion_prestada')->nullable();
            $table->string('urgencia', 20)->nullable()->comment('urgencia, prioritario, ordinario');
            $table->unsignedBigInteger('cita_id')->nullable();
            $table->jsonb('prestaciones_identificadas')->nullable();
            $table->timestamps();

            $table->index('ciudadano_id');
            $table->index('clasificacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sia_contactos');
    }
};

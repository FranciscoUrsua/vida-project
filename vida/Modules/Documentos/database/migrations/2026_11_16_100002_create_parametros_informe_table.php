<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros configurables de las plantillas de informe (variables auxiliares,
 * docs/instrucciones-cli/documentos-variables-auxiliares.md §1). Globales: un valor
 * por instalación. Sin baja lógica: son configuración, no datos de personas.
 */
return new class extends Migration
{
    /**
     * Crea la tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('parametros_informe', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->string('etiqueta', 200);
            $table->text('valor');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros_informe');
    }
};

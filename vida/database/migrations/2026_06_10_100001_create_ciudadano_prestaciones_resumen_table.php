<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de agregación de prestaciones del ciudadano sin historia social.
 *
 * No se consulta directamente a los módulos origen (Centros, Teleasistencia,
 * Prestaciones...). Cada módulo alimenta esta tabla mediante observers o
 * eventos de dominio cuando crea o cambia el estado de una prestación.
 * La FichaCiudadanoPage solo lee esta tabla (principio de desacoplamiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudadano_prestaciones_resumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->cascadeOnDelete();
            $table->string('modulo_origen');         // 'centros', 'teleasistencia', 'prestaciones'...
            $table->unsignedBigInteger('origen_id'); // id en la tabla origen
            $table->string('tipo');                  // clave de catálogo
            $table->string('descripcion');           // nombre legible, desnormalizado
            $table->string('estado');                // activo | en_tramite | finalizado | denegado | baja
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->index(['ciudadano_id', 'estado']);
            $table->index(['modulo_origen', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudadano_prestaciones_resumen');
    }
};

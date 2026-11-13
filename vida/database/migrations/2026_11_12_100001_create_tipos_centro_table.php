<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la entidad Tipo de Centro (docs/modulo-tipo-centro.md §2).
 *
 * El tipo es la fuente de la configuración común de sus centros: UO funcional,
 * reglas de asignación y parámetros del reparto. Sin soft deletes: la baja es
 * lógica (`activo`, `fecha_baja`) y los tipos nunca se borran (RN-11).
 */
return new class extends Migration
{
    /**
     * Crea la tabla `tipos_centro`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tipos_centro', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique()->comment('Clave interna inmutable (RN-02)');
            $table->string('nombre', 200)->comment('Caché de la denominación vigente');
            $table->string('siglas', 20)->comment('Caché de la denominación vigente');
            $table->foreignId('unidad_organizativa_id')->comment('UO funcional (RN-03)')
                ->constrained('unidades_organizativas')->restrictOnDelete();
            $table->boolean('inscripcion_libre')->default(false);
            $table->string('modo_asignacion_referencia', 20)->default('sorteo')
                ->comment('sorteo | libre_eleccion | quien_abre');
            $table->unsignedSmallInteger('ventana_reparto_meses')->default(12);
            $table->unsignedSmallInteger('meses_inactividad_caso')->default(6);
            $table->boolean('permite_restriccion_en_centro')->default(false)->comment('RN-08');
            $table->text('protocolo')->nullable();
            $table->boolean('activo')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
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
        Schema::dropIfExists('tipos_centro');
    }
};

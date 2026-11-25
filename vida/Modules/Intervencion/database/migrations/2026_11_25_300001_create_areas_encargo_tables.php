<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Intervencion\Database\Seeders\AreasEncargoSeeder;

/**
 * Área del encargo y su caja de herramientas (EA-1 a EA-3 de
 * docs/instrucciones-cli/2026-11-encargo-actuaciones.md).
 *
 * El área (jurídica, psicológica, formativa…) decide qué fichas de valoración, escalas y
 * plantillas de informe tiene a mano el profesional de encargo. El TSR la elige al encargar;
 * los encargos anteriores quedan sin área. La columna es nullable por ellos: la
 * obligatoriedad en los encargos nuevos la aplica EncargoService.
 *
 * Al final siembra las áreas iniciales (AreasEncargoSeeder, idempotente) para que lleguen a
 * staging con el despliegue.
 */
return new class extends Migration
{
    /**
     * Crea las áreas, sus tres catálogos y el área de cada encargo.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('areas_encargo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('area_encargo_tipo_ficha', function (Blueprint $table) {
            $table->foreignId('area_encargo_id')->constrained('areas_encargo')->cascadeOnDelete();
            $table->foreignId('tipo_ficha_id')->constrained('tipo_fichas')->cascadeOnDelete();
            $table->primary(['area_encargo_id', 'tipo_ficha_id']);
        });

        Schema::create('area_encargo_tipo_escala', function (Blueprint $table) {
            $table->foreignId('area_encargo_id')->constrained('areas_encargo')->cascadeOnDelete();
            $table->foreignId('tipo_escala_id')->constrained('tipo_escalas')->cascadeOnDelete();
            $table->primary(['area_encargo_id', 'tipo_escala_id']);
        });

        Schema::create('area_encargo_plantilla_informe', function (Blueprint $table) {
            $table->foreignId('area_encargo_id')->constrained('areas_encargo')->cascadeOnDelete();
            $table->foreignId('plantilla_informe_id')->constrained('plantillas_informe')->cascadeOnDelete();
            $table->primary(['area_encargo_id', 'plantilla_informe_id']);
        });

        Schema::table('derivaciones', function (Blueprint $table) {
            $table->foreignId('area_encargo_id')->nullable()->after('modalidad')->constrained('areas_encargo');
        });

        (new AreasEncargoSeeder)->run();
    }

    /**
     * Retira el área de los encargos y las tablas de áreas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_encargo_id');
        });

        Schema::dropIfExists('area_encargo_plantilla_informe');
        Schema::dropIfExists('area_encargo_tipo_escala');
        Schema::dropIfExists('area_encargo_tipo_ficha');
        Schema::dropIfExists('areas_encargo');
    }
};

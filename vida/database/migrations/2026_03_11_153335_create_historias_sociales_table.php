<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración stub para la tabla de Historias Sociales.
 *
 * Crea la estructura mínima necesaria para que las Policies y sus tests
 * puedan funcionar. La implementación completa corresponde al módulo
 * Intervencion (docs/glosario.md § Historia Social).
 *
 * @todo Mover a Modules\Intervencion cuando se implemente ese módulo,
 *       con todos los campos y relaciones de dominio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historias_sociales', function (Blueprint $table) {
            $table->id();

            // TODO: FK a tabla ciudadanos cuando se implemente el módulo Ciudadanía
            $table->unsignedBigInteger('ciudadano_id')->comment('Ciudadano titular de la Historia');

            $table->foreignId('unidad_organizativa_id')
                ->constrained('unidades_organizativas')
                ->onDelete('restrict')
                ->comment('UO responsable de la Historia');

            $table->boolean('ciudadano_protegido')
                ->default(false)
                ->comment('El ciudadano pertenece a un colectivo especialmente protegido');

            // TODO: convertir en FK a tabla estados_historia cuando se configure el backoffice
            $table->string('estado', 30)
                ->default('abierta')
                ->comment('Estado: abierta, en_seguimiento, cerrada');

            $table->timestamps();
            $table->softDeletes();

            $table->index('ciudadano_id');
            $table->index('unidad_organizativa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historias_sociales');
    }
};

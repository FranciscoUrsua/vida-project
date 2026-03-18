<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de valores clasificatorios configurables desde backoffice.
 *
 * Implementa el principio 3.10 de principios-vida360.md: los valores puramente
 * descriptivos o clasificatorios — que un administrador funcional puede añadir,
 * renombrar u ordenar sin necesidad de un deploy — se gestionan aquí.
 *
 * Grupos iniciales: prestacion.objetivo_general, prestacion.categoria,
 * prestacion.nivel_atencion, prestacion.competencia, prestacion.forma_gestion,
 * prestacion.financiacion, prestacion.poblacion, centro.tipo
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogos_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('grupo');       // ej: 'prestacion.objetivo_general'
            $table->string('clave');       // ej: '01'
            $table->string('etiqueta');    // ej: 'Acceso, información y valoración'
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['grupo', 'clave']);
            $table->index('grupo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogos_sistema');
    }
};

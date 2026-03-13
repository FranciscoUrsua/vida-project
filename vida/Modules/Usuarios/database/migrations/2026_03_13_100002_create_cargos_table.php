<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: catálogo de cargos profesionales.
 *
 * Catálogo configurable desde el backoffice.
 * Ejemplos: Trabajador/a Social, Psicólogo/a, Educador/a Social.
 *
 * @see \Modules\Usuarios\Models\Cargo
 * @see docs/modulo-usuarios-permisos.md sección 5.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 150)
                ->comment('Nombre del cargo: "Trabajador/a Social", "Técnico/a de Acogida"...');

            $table->text('descripcion')
                ->nullable()
                ->comment('Descripción del cargo (opcional)');

            $table->boolean('activo')
                ->default(true)
                ->comment('Permite desactivar cargos obsoletos sin eliminarlos');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};

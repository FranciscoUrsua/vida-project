<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: roles sugeridos por cargo.
 *
 * Cada fila propone un rol de Spatie (por nombre) para los usuarios que se dan
 * de alta con un profesional de ese cargo. Es una ayuda para el alta, no una
 * fuente de permisos: nada fuera del formulario de alta y del aviso de cambio
 * de cargo lee esta tabla.
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
return new class extends Migration
{
    /**
     * Crea la tabla cargo_roles_sugeridos.
     */
    public function up(): void
    {
        Schema::create('cargo_roles_sugeridos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')
                ->constrained('cargos')
                ->cascadeOnDelete()
                ->comment('Cargo al que se asocia la sugerencia');
            $table->string('rol', 125)
                ->comment('Nombre del rol Spatie sugerido');
            $table->timestamps();

            $table->unique(['cargo_id', 'rol']);
        });
    }

    /**
     * Elimina la tabla cargo_roles_sugeridos.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo_roles_sugeridos');
    }
};

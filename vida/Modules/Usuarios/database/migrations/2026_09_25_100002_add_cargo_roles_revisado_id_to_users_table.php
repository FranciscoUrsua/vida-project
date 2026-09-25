<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: cargo para el que se revisaron por última vez los roles del usuario.
 *
 * Si el cargo actual del profesional vinculado difiere de este valor, la ficha
 * del usuario en Filament muestra el aviso «El cargo ha cambiado». Se actualiza
 * al dar de alta, al editar los roles o al descartar el aviso. Nunca provoca
 * cambios de rol por sí mismo.
 *
 * @see docs/modulo-usuarios-permisos.md sección 2.9
 */
return new class extends Migration
{
    /**
     * Añade la columna y la rellena con el cargo actual de cada usuario.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cargo_roles_revisado_id')
                ->nullable()
                ->after('profesional_id')
                ->constrained('cargos')
                ->nullOnDelete()
                ->comment('Cargo para el que se revisaron los roles; si difiere del actual se muestra el aviso');
        });

        // Los usuarios existentes parten revisados con su cargo actual: sin esto,
        // todos mostrarían el aviso de cambio de cargo nada más migrar.
        DB::statement(<<<'SQL'
            UPDATE users
            SET cargo_roles_revisado_id = profesionales.cargo_id
            FROM profesionales
            WHERE profesionales.id = users.profesional_id
        SQL);
    }

    /**
     * Elimina la columna.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargo_roles_revisado_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los perfiles horarios creados por defecto con el horario del centro
 * («horario no personalizado») hasta que el supervisor los revisa y guarda.
 * Los perfiles existentes los ha configurado alguien: quedan verificados.
 */
return new class extends Migration
{
    /**
     * Añade la columna `pendiente_verificar`.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('perfiles_horario_profesional', function (Blueprint $table) {
            $table->boolean('pendiente_verificar')->default(false)->after('activo');
        });
    }

    /**
     * Elimina la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('perfiles_horario_profesional', function (Blueprint $table) {
            $table->dropColumn('pendiente_verificar');
        });
    }
};

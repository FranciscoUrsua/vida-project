<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina rol_id de usuario_uo_rol.
 *
 * Los roles son globales al usuario (Spatie model_has_roles).
 * La adscripción a UO solo define dónde opera el usuario,
 * no qué rol tiene en ese ámbito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_uo_rol', function (Blueprint $table) {
            $table->dropForeign(['rol_id']);
            $table->dropColumn('rol_id');
        });
        Schema::rename('usuario_uo_rol', 'usuario_uo');
    }

    public function down(): void
    {
        Schema::rename('usuario_uo', 'usuario_uo_rol');
        Schema::table('usuario_uo_rol', function (Blueprint $table) {
            $table->foreignId('rol_id')->constrained('roles');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: añade profesional_id a la tabla users.
 *
 * Vincula una cuenta de acceso (User) con su perfil organizativo
 * (Profesional). La FK es nullable:
 *
 * - Un User puede no tener Profesional: personal técnico de sistema
 *   (rol adm_sistema) sin función asistencial directa.
 * - Un Profesional puede no tener User: monitores, voluntarios,
 *   personal sin necesidad de acceso al sistema.
 *
 * La FK vive en users (no en profesionales) para navegar desde
 * el usuario hacia su perfil cuando existe.
 *
 * @see docs/modulo-usuarios-permisos.md sección 1.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profesional_id')
                ->nullable()
                ->after('id')
                ->constrained('profesionales')
                ->onDelete('restrict')
                ->comment('Perfil organizativo del usuario. Nullable para perfiles técnicos sin función asistencial');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profesional_id']);
            $table->dropColumn('profesional_id');
        });
    }
};

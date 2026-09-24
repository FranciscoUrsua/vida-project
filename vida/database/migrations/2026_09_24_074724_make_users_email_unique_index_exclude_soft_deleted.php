<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: el índice único de `email` excluye a los usuarios con soft delete.
 *
 * Con `users_email_unique` como índice único normal, borrar (soft delete)
 * un usuario dejaba su email bloqueado para siempre: cualquier intento de
 * crear una cuenta nueva con ese email fallaba con
 * UniqueConstraintViolationException, aunque la cuenta original estuviera
 * desactivada. Se sustituye por un índice único parcial (solo sobre filas
 * con `deleted_at IS NULL`), específico de PostgreSQL — único motor que
 * usa este proyecto (ver docs/decisiones-tecnicas.md).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};

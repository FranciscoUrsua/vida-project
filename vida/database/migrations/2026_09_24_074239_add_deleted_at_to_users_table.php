<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: soft delete en users.
 *
 * Sin esta columna, borrar un usuario desde Filament intentaba un DELETE
 * físico que fallaba con QueryException por las FK de otras tablas
 * (roles, adscripciones, auditoría, informes, etc.) que referencian users.
 * Principio 4.2 de VIDA 360: soft deletes como norma en entidades de dominio.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

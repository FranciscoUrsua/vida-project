<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade slug (identificador estable único) a tipos_actividad.
 *
 * activo ya existe en la tabla desde la migración original; slug es nuevo.
 * Se rellena con un valor temporal antes de imponer la restricción UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_actividad', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_actividad', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};

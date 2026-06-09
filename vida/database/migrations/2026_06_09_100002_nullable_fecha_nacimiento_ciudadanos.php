<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hace nullable fecha_nacimiento en ciudadanos.
 *
 * La especificación de alta indica que fecha de nacimiento es opcional (no en PSH
 * sin documentación formal). La migración original la declaró NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->text('fecha_nacimiento')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ciudadanos', function (Blueprint $table) {
            $table->text('fecha_nacimiento')->nullable(false)->change();
        });
    }
};

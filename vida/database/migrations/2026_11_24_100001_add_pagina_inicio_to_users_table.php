<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencia de página de inicio del usuario (R4.6 de la ronda 1 de staging).
 *
 * Guarda la clave de una superficie operativa (`App\Support\Superficie\Superficie`); `null` es
 * «Automática», la prioridad de R4.5.
 */
return new class extends Migration
{
    /**
     * Añade la columna.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pagina_inicio', 32)->nullable()->after('primer_acceso');
        });
    }

    /**
     * Quita la columna.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('pagina_inicio');
        });
    }
};

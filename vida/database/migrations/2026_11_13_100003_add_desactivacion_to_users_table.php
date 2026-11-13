<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RN-20 (docs/modulo-tipo-centro.md): los usuarios no se borran, se desactivan.
 * Un usuario desactivado no puede iniciar sesión y conserva su historial.
 *
 * Solo añade columnas nullable. Las cuentas con `deleted_at` anteriores a esta
 * decisión (13 usuarios de prueba en la BD compartida) se dejan como están:
 * decisión del desarrollador del 13 nov 2026.
 */
return new class extends Migration
{
    /**
     * Añade las columnas de desactivación.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('desactivado_en')->nullable()->after('primer_acceso')
                ->comment('Desactivación (RN-20): sin acceso; null si está activo');
            $table->foreignId('desactivado_por_id')->nullable()->after('desactivado_en')
                ->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Quita las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desactivado_por_id');
            $table->dropColumn('desactivado_en');
        });
    }
};

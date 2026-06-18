<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade schema_snapshot y profesional_id a la tabla fichas.
 *
 * schema_snapshot: copia del schema del TipoFicha en el momento de creación.
 *   Hace la ficha autocontenida: puede interpretarse con independencia de
 *   cómo evolucione el TipoFicha en el futuro. Nullable para no romper
 *   fichas existentes en desarrollo; se rellena via backfill si procede.
 *
 * profesional_id: FK al usuario que cumplimentó la ficha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas', function (Blueprint $table): void {
            $table->jsonb('schema_snapshot')->nullable()->after('tipo_ficha_id');
            $table->foreignId('profesional_id')
                ->nullable()
                ->after('schema_snapshot')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fichas', function (Blueprint $table): void {
            $table->dropForeign(['profesional_id']);
            $table->dropColumn(['schema_snapshot', 'profesional_id']);
        });
    }
};

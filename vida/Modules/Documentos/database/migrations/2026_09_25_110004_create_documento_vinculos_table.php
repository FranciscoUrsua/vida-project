<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculos n:M de documentos con personas y, según el tipo, intervenciones o valoraciones (paso 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos');
            $table->morphs('vinculable');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_alta');
            $table->timestamp('fecha_baja')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->foreignId('baja_por')->nullable()->constrained('users');
            $table->timestamps();
        });

        // Un documento no puede tener dos vínculos activos con la misma entidad.
        DB::statement('CREATE UNIQUE INDEX documento_vinculos_activo_unico ON documento_vinculos (documento_id, vinculable_type, vinculable_id) WHERE activo = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_vinculos');
    }
};

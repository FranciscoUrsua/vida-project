<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de versiones de perfiles de anonimización.
 *
 * Cada fila es un snapshot del estado de 'campos' y 'k_valor' ANTES de un cambio.
 * El registro en perfiles_anonimizacion tiene siempre el estado actual.
 * Los registros de esta tabla son inmutables — no tienen updated_at.
 *
 * Una extracción registra la versión del perfil aplicada. Esa combinación
 * (perfil_id + version) permite reconstruir exactamente qué transformación
 * se aplicó a cualquier extracción pasada. Ver docs/anonimizacion.md § 6.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfil_anonimizacion_versiones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('perfil_id')
                ->constrained('perfiles_anonimizacion')
                ->cascadeOnDelete();

            // Número de versión que tenía el perfil antes del cambio
            $table->unsignedInteger('version');

            // Snapshot de configuración
            $table->json('campos');
            $table->unsignedInteger('k_valor')->nullable();

            // Solo created_at — los snapshots son inmutables
            $table->timestamp('created_at');

            $table->index(['perfil_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfil_anonimizacion_versiones');
    }
};

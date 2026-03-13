<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de versiones históricas (polimórfica).
 *
 * Almacena snapshots completos del estado de cualquier entidad
 * antes de cada modificación. El snapshot es el estado ANTERIOR
 * al cambio; el registro principal siempre tiene el estado actual.
 *
 * Usada por el trait Versionable en Profesional, Ciudadano, Centro
 * y cualquier entidad no auxiliar con historial relevante.
 *
 * @see \App\Traits\Versionable
 * @see docs/modulo-usuarios-permisos.md sección 5.4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones', function (Blueprint $table) {
            $table->id();

            $table->string('versionable_type')
                ->comment('Clase del modelo versionado');
            $table->unsignedBigInteger('versionable_id')
                ->comment('ID del registro versionado');

            $table->json('datos')
                ->comment('Snapshot completo del registro antes del cambio');

            $table->unsignedBigInteger('usuario_id')
                ->nullable()
                ->comment('Usuario que realizó el cambio');

            $table->text('motivo')
                ->nullable()
                ->comment('Justificación del cambio cuando se requiere');

            // Solo created_at — el timestamp ES la fecha de la versión
            $table->timestamp('created_at')->useCurrent();

            // Índice compuesto para consultas históricas eficientes
            $table->index(['versionable_type', 'versionable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones');
    }
};

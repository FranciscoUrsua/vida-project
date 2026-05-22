<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría de revelaciones de identidad.
 *
 * Cada fila documenta un acceso de reversión de seudonimización: quién,
 * cuándo, sobre qué alias y con qué justificación. Los registros son
 * inmutables — sin updated_at. Ver docs/anonimizacion.md § 6.3.
 *
 * Esta tabla es la 'tabla de correspondencias' que nunca sale del sistema:
 * el alias se computa en vuelo para la búsqueda, pero el acceso queda aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revelaciones_identidad', function (Blueprint $table) {
            $table->id();

            // Usuario que realizó la revelación
            $table->foreignId('usuario_id')->constrained('users');

            // Siempre 'revelar_identidad' — campo presente para extensibilidad futura de auditoría
            $table->string('accion', 50)->default('revelar_identidad');

            // Alias que se resolvió
            $table->string('alias', 20);

            // Ciudadano cuya identidad se reveló (sin FK para no crear dependencia de integridad)
            $table->unsignedBigInteger('ciudadano_id');

            // Justificación obligatoria — requerimiento legal
            $table->text('justificacion');

            // Solo created_at — la revelación es inmutable
            $table->timestamp('created_at');

            $table->index(['usuario_id', 'created_at']);
            $table->index('ciudadano_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revelaciones_identidad');
    }
};

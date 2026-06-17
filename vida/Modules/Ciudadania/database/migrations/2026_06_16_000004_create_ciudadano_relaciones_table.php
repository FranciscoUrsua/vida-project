<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciones entre ciudadanos.
 *
 * tipo_relacion referencia el slug del catálogo tipos_relacion (FK lógica,
 * no constraint real para permitir edición de slugs sin cascadas).
 * La reciprocidad la gestiona el trait TieneRelacionesReciprocas en el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudadano_relaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->cascadeOnDelete();
            $table->foreignId('ciudadano_relacionado_id')
                ->constrained('ciudadanos')
                ->cascadeOnDelete();
            $table->string('tipo_relacion');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('ciudadano_id');
            $table->index('ciudadano_relacionado_id');
            $table->index('tipo_relacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudadano_relaciones');
    }
};

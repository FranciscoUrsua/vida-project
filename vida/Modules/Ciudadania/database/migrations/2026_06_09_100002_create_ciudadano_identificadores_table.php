<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de identificadores documentales del ciudadano.
 *
 * Un ciudadano puede tener múltiples documentos a lo largo del tiempo
 * (p.ej. pasaporte → NIE → DNI). Todos se preservan para trazabilidad
 * de búsquedas retrospectivas.
 *
 * El campo `valor` está cifrado en aplicación. `valor_hash` es el hash
 * determinista SHA-256 del valor normalizado en minúsculas, usado para
 * búsquedas sin necesidad de descifrar.
 *
 * Ver docs/modulo-ciudadania.md § 3.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudadano_identificadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos')->cascadeOnDelete();

            // Tipo de documento — configurable desde backoffice
            $table->string('tipo', 30)->comment('nif | nie | pasaporte | ni_hsu_cm | id_legacy_otro');

            // Valor cifrado e índice de búsqueda
            $table->text('valor')->comment('Valor del documento — cifrado en aplicación');
            $table->string('valor_hash', 64)->comment('SHA-256(strtolower(valor)) — índice de búsqueda');

            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('verificado')->default(false)
                ->comment('true si el profesional ha visto físicamente el documento');
            $table->string('fuente', 30)->default('manual')
                ->comment('manual | padron | ciudadano360 | importacion | interoperabilidad');
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->index('valor_hash');
            $table->index(['ciudadano_id', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudadano_identificadores');
    }
};

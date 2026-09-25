<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Documentos\Enums\FamiliaDocumental;
use Modules\Documentos\Enums\OrigenEni;
use Modules\Documentos\Enums\PoliticaVersiones;

/**
 * Tipos documentales configurables en Filament (custodia v2, paso 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_documentales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 200);
            $table->enum('familia', FamiliaDocumental::valores());
            $table->enum('origen_eni', OrigenEni::valores());
            $table->boolean('caduca')->default(false);
            $table->integer('validez_dias')->nullable();
            $table->enum('politica_versiones', PoliticaVersiones::valores())->default(PoliticaVersiones::Conservar->value);
            $table->integer('conservacion_anyos')->nullable()
                ->comment('null = sin plazo definido: nunca se propone su destrucción');
            $table->boolean('visible_ciudadano_defecto')->default(false);
            $table->boolean('requiere_firma')->default(false);
            $table->bigInteger('max_bytes');
            $table->integer('max_paginas');
            $table->jsonb('metadatos_requeridos')->default('[]');
            $table->jsonb('vinculables')->default('["ciudadano"]');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_documentales');
    }
};

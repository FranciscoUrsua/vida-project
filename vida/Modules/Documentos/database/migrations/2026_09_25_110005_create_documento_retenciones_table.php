<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Documentos\Enums\MotivoRetencion;

/**
 * Retenciones que impiden purgar o destruir un documento o una versión concreta (paso 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_retenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos');
            $table->foreignId('documento_version_id')->nullable()->constrained('documento_versiones');
            $table->enum('motivo', MotivoRetencion::valores());
            $table->nullableMorphs('retenedor');
            $table->timestamp('desde');
            $table->timestamp('hasta')->nullable()->comment('null = retención indefinida');
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();

            $table->index(['documento_id', 'desde', 'hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_retenciones');
    }
};

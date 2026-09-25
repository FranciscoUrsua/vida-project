<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actas de eliminación de documentos: constancia inmutable de lo destruido (paso 2).
 *
 * El detalle solo guarda metadatos de cada versión destruida, nunca contenido ni nombre original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas_eliminacion', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->comment('Correlativo por año: AAAA/NNNN');
            $table->foreignId('aprobada_por')->constrained('users');
            $table->timestamp('aprobada_en');
            $table->text('motivo');
            $table->jsonb('detalle');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas_eliminacion');
    }
};

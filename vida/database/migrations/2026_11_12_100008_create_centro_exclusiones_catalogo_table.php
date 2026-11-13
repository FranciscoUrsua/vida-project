<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exclusiones del catálogo del tipo en un centro (RN-08, docs/modulo-tipo-centro.md §6.3).
 *
 * Solo cuentan si el tipo permite la restricción en centro; en Madrid no se usa.
 */
return new class extends Migration
{
    /**
     * Crea la tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('centro_exclusiones_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->string('elemento_tipo', 20)->comment('prestacion | tipo_plan | tipo_ficha');
            $table->unsignedBigInteger('elemento_id');
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['centro_id', 'elemento_tipo', 'elemento_id']);
        });
    }

    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('centro_exclusiones_catalogo');
    }
};

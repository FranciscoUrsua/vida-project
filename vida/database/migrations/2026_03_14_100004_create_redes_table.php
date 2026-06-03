<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Red;

/**
 * Redes de centros.
 *
 * Una red agrupa centros que comparten un pool de plazas o
 * una lista de espera unificada. Por ejemplo, "Red de Albergues
 * Municipales" o "Red de Centros de Día para Mayores".
 *
 * @see Red
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('nombre_corto', 60)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\InscripcionCentro;

/**
 * Inscripciones de ciudadanos a centros.
 *
 * Registro de la adscripción de un ciudadano a un centro.
 * Es requisito para actividades con `requiere_inscripcion_centro = true`.
 * Historial acumulativo: no se borran, se cierran con fecha_baja.
 *
 * @see InscripcionCentro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones_centro', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->restrictOnDelete();

            $table->foreignId('ciudadano_id')
                ->constrained('ciudadanos')
                ->restrictOnDelete();

            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable()->comment('Baja siempre explícita');
            $table->string('motivo_baja', 200)->nullable();
            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['centro_id', 'activa']);
            $table->index(['ciudadano_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones_centro');
    }
};

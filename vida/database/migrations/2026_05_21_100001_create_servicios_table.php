<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Servicio;

/**
 * Nodos de tramitación de prestaciones.
 *
 * Un servicio tramita prestaciones administrativas para ciudadanos; no tiene
 * presencia física relevante ni plazas. La dirección de referencia se obtiene
 * de su UO. La UO es obligatoria (no nullable).
 *
 * cargo_nombre: nombre del cargo del responsable definido a nivel del servicio
 * (ej: "Jefe de Servicio de Ayuda a Domicilio"). El profesional que ocupe el
 * cargo ostenta ese título mientras lo dirija.
 *
 * @see Servicio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 255);
            $table->string('nombre_corto', 100);

            $table->foreignId('unidad_organizativa_id')
                ->constrained('unidades_organizativas')
                ->restrictOnDelete()
                ->comment('Obligatorio. El servicio hereda la dirección de su UO');

            $table->string('cargo_nombre', 255)
                ->comment('Nombre del cargo del responsable, definido a nivel del servicio');

            $table->text('descripcion')->nullable();

            $table->boolean('activo')->default(true);
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'fecha_baja']);
            $table->index('unidad_organizativa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};

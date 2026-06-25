<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espacios funcionales de un centro (aulas, salas de reuniones, despachos...).
 *
 * Entidad distinta de Espacio (jerarquía ColeccionPlazas → Espacio → Plaza).
 * Las salas no tienen plazas asignables; se referencian desde SesionActividad
 * como dato informativo de ubicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('capacidad')->nullable()
                ->comment('Número de personas, no plazas de alojamiento');
            $table->boolean('accesible')->default(false);
            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index('centro_id');
            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};

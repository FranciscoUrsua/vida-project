<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\ContactoCentro;

/**
 * Contactos adicionales de un centro.
 *
 * Personas de contacto para gestiones: trabajadora social de referencia,
 * administración, responsable de guardia, etc.
 *
 * @see ContactoCentro
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos_centro', function (Blueprint $table) {
            $table->id();

            $table->foreignId('centro_id')
                ->constrained('centros')
                ->cascadeOnDelete();

            $table->string('nombre', 200);
            $table->string('rol', 100)->nullable()
                ->comment('Ej: "Coordinador", "Trabajador social de referencia"');
            $table->string('telefono', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index('centro_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos_centro');
    }
};

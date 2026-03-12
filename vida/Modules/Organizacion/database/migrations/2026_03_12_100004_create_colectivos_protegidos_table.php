<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de colectivos especialmente protegidos.
 *
 * Define los colectivos cuyos ciudadanos requieren aprobación previa
 * para el acceso desde fuera de la UO responsable (principio 3.6).
 * Actualmente: menores y víctimas de violencia de género.
 * Es configurable desde el backoffice (principio 4.15).
 *
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colectivos_protegidos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre del colectivo, ej: Menores');
            $table->text('descripcion')->comment('Descripción y criterios de pertenencia al colectivo');
            $table->boolean('requiere_aprobacion_previa')->default(true)
                ->comment('Si true, el acceso desde fuera de UO requiere aprobación del supervisor');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colectivos_protegidos');
    }
};

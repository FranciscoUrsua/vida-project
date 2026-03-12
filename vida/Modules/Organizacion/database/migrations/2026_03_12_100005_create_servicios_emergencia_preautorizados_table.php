<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de servicios de emergencia preautorizados.
 *
 * Los servicios listados aquí tienen acceso preautorizado en modo consulta
 * a Historias de ciudadanos especialmente protegidos, sin aprobación previa.
 * El acceso queda registrado en auditoría (principio 3.6 excepción urgencia).
 * Por defecto: SAMUR Social de Madrid.
 * Es configurable desde el backoffice (principio 4.15).
 *
 * @see docs/modulo-usuarios-permisos.md sección 3 (Excepción: servicios de urgencia)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios_emergencia_preautorizados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->comment('Nombre del servicio, ej: SAMUR Social');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_emergencia_preautorizados');
    }
};

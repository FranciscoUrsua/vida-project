<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de configuración de roles.
 *
 * Extiende los roles de Spatie sin modificar sus tablas.
 * Almacena atributos adicionales de cada rol que son configurables
 * desde el backoffice, actualmente el nivel de supervisión requerido
 * para que la asignación de ese rol sea efectiva.
 *
 * nivel_supervision:
 * - aprobacion_previa: la asignación no es efectiva hasta que el
 *   supervisor de la UO superior la aprueba. Aplica a adm_sistema y supervision.
 * - alerta_supervisada: la asignación es efectiva inmediatamente y
 *   se genera una alerta que el supervisor debe reconocer. Resto de roles.
 *
 * @see \Modules\Usuarios\Models\ConfiguracionRol
 * @see docs/modulo-usuarios-permisos.md sección 2.8
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_roles', function (Blueprint $table) {
            $table->id();

            // FK a la tabla roles de Spatie laravel-permission
            $table->unsignedBigInteger('rol_id')
                ->unique()
                ->comment('Rol al que aplica esta configuración (FK a roles de Spatie)');
            $table->foreign('rol_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->string('nivel_supervision', 30)
                ->default('alerta_supervisada')
                ->comment('aprobacion_previa: activa el flujo de aprobación; alerta_supervisada: efectiva inmediatamente con alerta');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_roles');
    }
};

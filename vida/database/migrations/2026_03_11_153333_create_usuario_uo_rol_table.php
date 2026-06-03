<?php

use App\Models\UsuarioUoRol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla pivot de adscripción usuario–UO–rol.
 *
 * Vincula a un usuario con una Unidad Organizativa y un rol de Spatie,
 * incluyendo el tipo de vínculo laboral y las fechas de vigencia.
 * Permite mantener historial completo de adscripciones pasadas
 * (principio 4.2: el pasado es inmutable).
 *
 * Un usuario puede tener múltiples registros activos (varios roles
 * en varias UO). Los permisos efectivos son la unión de todos los roles
 * activos (docs/modulo-usuarios-permisos.md sección 1.3).
 *
 * @see UsuarioUoRol
 */
return new class extends Migration
{
    /**
     * Crea la tabla `usuario_uo_rol`.
     */
    public function up(): void
    {
        Schema::create('usuario_uo_rol', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Profesional adscrito');

            $table->foreignId('unidad_organizativa_id')
                ->constrained('unidades_organizativas')
                ->onDelete('restrict')
                ->comment('UO a la que se adscribe');

            // FK a la tabla roles de Spatie laravel-permission
            $table->unsignedBigInteger('rol_id')
                ->comment('Rol asignado en esta UO (FK a roles de Spatie)');
            $table->foreign('rol_id')
                ->references('id')
                ->on('roles')
                ->onDelete('restrict');

            // Tipo de vínculo laboral — configurable via tabla auxiliar en el futuro
            // TODO: valorar si convertir este campo en FK a una tabla tipos_vinculo
            $table->string('tipo_vinculo', 50)
                ->default('interno')
                ->comment('Tipo de vínculo laboral: interno (funcionario/laboral) o contratado (empresa externa)');

            $table->date('fecha_inicio')->comment('Inicio de la adscripción');
            $table->date('fecha_fin')->nullable()->comment('Fin de la adscripción. Null = vigente');

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index('usuario_id');
            $table->index('unidad_organizativa_id');
            $table->index(['usuario_id', 'fecha_fin']);
        });
    }

    /**
     * Elimina la tabla `usuario_uo_rol`.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_uo_rol');
    }
};

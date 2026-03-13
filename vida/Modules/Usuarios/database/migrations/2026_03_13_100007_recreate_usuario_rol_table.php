<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: recrea usuario_rol con el esquema definitivo.
 *
 * Cambios respecto a la versión anterior:
 * - user_id → usuario_id (nomenclatura consistente)
 * - rol (string) → rol_id (FK a roles de Spatie)
 * - Añade campo asignado_por (FK al usuario que asignó el rol)
 * - Añade campo estado (pendiente_aprobacion / activo / inactivo)
 *   para el flujo de aprobación previa en roles críticos.
 *
 * La tabla es la FUENTE DE VERDAD del historial de roles.
 * model_has_roles de Spatie es el estado derivado activo.
 * El Observer mantiene ambas sincronizadas.
 *
 * @see \Modules\Usuarios\Models\UsuarioRol
 * @see \Modules\Usuarios\Observers\UsuarioRolObserver
 * @see docs/modulo-usuarios-permisos.md secciones 4.2 y 4.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('usuario_rol');

        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario al que se asigna el rol');

            // FK a la tabla roles de Spatie laravel-permission
            $table->unsignedBigInteger('rol_id')
                ->comment('Rol asignado (FK a roles de Spatie)');
            $table->foreign('rol_id')
                ->references('id')
                ->on('roles')
                ->onDelete('restrict');

            $table->date('fecha_inicio')
                ->comment('Inicio de la vigencia del rol');

            $table->date('fecha_fin')
                ->nullable()
                ->comment('Fin de la vigencia del rol. Null = vigente');

            $table->foreignId('asignado_por')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que realizó la asignación');

            $table->string('estado', 30)
                ->default('activo')
                ->comment('pendiente_aprobacion: esperando aprobación del supervisor; activo: vigente; inactivo: revocado');

            $table->timestamps();

            // Índices para las consultas más frecuentes
            $table->index('usuario_id');
            $table->index(['usuario_id', 'estado', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');

        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('rol', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index(['user_id', 'fecha_fin']);
        });
    }
};

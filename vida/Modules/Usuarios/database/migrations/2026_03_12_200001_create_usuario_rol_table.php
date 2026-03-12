<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de historial de roles de usuarios.
 *
 * Registra qué roles ha tenido un usuario a lo largo del tiempo,
 * con fechas de vigencia para mantener el historial completo
 * (principio 4.2: el pasado es inmutable).
 *
 * Esta tabla es COMPLEMENTARIA a model_has_roles de Spatie:
 * - model_has_roles: refleja los roles ACTIVOS AHORA (sincronizado)
 * - usuario_rol: historial completo con fechas de inicio y fin
 *
 * @see \Modules\Usuarios\Models\UsuarioRol
 * @see docs/modulo-usuarios-permisos.md sección 4.2
 */
return new class extends Migration
{
    /**
     * Crea la tabla `usuario_rol`.
     */
    public function up(): void
    {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario al que se asigna el rol');

            $table->string('rol', 100)
                ->comment('Nombre del rol de Spatie, ej: intervencion, supervision');

            $table->date('fecha_inicio')
                ->comment('Inicio de la vigencia del rol');

            $table->date('fecha_fin')
                ->nullable()
                ->comment('Fin de la vigencia del rol. Null = vigente actualmente');

            $table->timestamps();

            // Índices para las consultas más frecuentes
            $table->index('user_id');
            $table->index(['user_id', 'fecha_fin']);
        });
    }

    /**
     * Elimina la tabla `usuario_rol`.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');
    }
};

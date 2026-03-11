<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de solicitudes de acceso a colectivos especialmente protegidos.
 *
 * Implementa el flujo de aprobación descrito en la sección 3 de
 * docs/modulo-usuarios-permisos.md: un profesional solicita acceso
 * a la Historia de un ciudadano protegido fuera de su UO; el supervisor
 * competente aprueba o deniega.
 *
 * Los estados posibles son configurables (principio 4.12); los valores
 * iniciales (pendiente, aprobado, denegado) se corresponden con las
 * fases del flujo definido en el documento de especificación.
 *
 * @see \App\Policies\HistoriaSocialPolicy
 * @see docs/modulo-usuarios-permisos.md sección 3
 */
return new class extends Migration
{
    /**
     * Crea la tabla `accesos_protegidos`.
     */
    public function up(): void
    {
        Schema::create('accesos_protegidos', function (Blueprint $table) {
            $table->id();

            // Profesional que solicita el acceso
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Profesional que solicita el acceso');

            // Ciudadano cuya Historia se quiere consultar
            // TODO: reemplazar por FK a la tabla ciudadanos cuando se implemente
            $table->unsignedBigInteger('ciudadano_id')
                ->comment('Ciudadano especialmente protegido al que se solicita acceso');

            // Quién activa la solicitud (puede ser distinto del solicitante: supervisor delegando)
            $table->foreignId('solicitante_id')
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Usuario que realiza la solicitud formalmente (normalmente = usuario_id)');

            $table->text('justificacion')
                ->comment('Justificación obligatoria de la necesidad de acceso');

            // TODO: convertir en FK a tabla estados_acceso_protegido cuando se añada
            // configuración de estados desde backoffice (principio 4.12)
            $table->string('estado', 30)
                ->default('pendiente')
                ->comment('Estado de la solicitud: pendiente, aprobado, denegado');

            // Supervisor que resuelve la solicitud
            $table->foreignId('aprobado_por')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict')
                ->comment('Supervisor que aprueba o deniega la solicitud');

            $table->timestamp('fecha_resolucion')
                ->nullable()
                ->comment('Momento en que el supervisor resuelve la solicitud');

            // Vigencia del acceso concedido (null = sin límite definido por el supervisor)
            $table->timestamp('acceso_valido_hasta')
                ->nullable()
                ->comment('Fecha límite del acceso aprobado. Null = hasta nueva resolución');

            $table->timestamps();

            // Índices para las consultas más habituales
            $table->index('usuario_id');
            $table->index('ciudadano_id');
            $table->index(['usuario_id', 'ciudadano_id', 'estado']);
        });
    }

    /**
     * Elimina la tabla `accesos_protegidos`.
     */
    public function down(): void
    {
        Schema::dropIfExists('accesos_protegidos');
    }
};

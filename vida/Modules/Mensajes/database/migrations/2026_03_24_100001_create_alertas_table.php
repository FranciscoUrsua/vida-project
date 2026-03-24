<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de alertas del sistema.
 *
 * Almacena tanto avisos (informativos, sin reconocimiento) como alertas
 * (requieren reconocimiento explícito en 4h laborales, con escalada al
 * supervisor si vencen sin reconocer).
 *
 * @see docs/modulo-mensajes.md § 2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();

            $table->enum('tipo', ['aviso', 'alerta'])->comment('Nivel de gravedad');

            // Origen polimórfico: módulo que generó la alerta
            $table->string('origen_type')->comment('Clase del modelo generador');
            $table->unsignedBigInteger('origen_id')->comment('ID del objeto generador');

            $table->string('titulo')->comment('Texto corto para listados');
            $table->text('cuerpo')->comment('Contenido completo de la alerta');

            // Destinatario
            $table->enum('destinatario_type', ['usuario', 'rol_uo'])
                ->comment('Tipo de destinatario');
            $table->unsignedBigInteger('destinatario_usuario_id')->nullable()
                ->comment('Usuario destinatario cuando destinatario_type = usuario');
            $table->string('destinatario_rol', 100)->nullable()
                ->comment('Rol objetivo cuando destinatario_type = rol_uo');
            $table->unsignedBigInteger('destinatario_uo_id')->nullable()
                ->comment('UO objetivo cuando destinatario_type = rol_uo');

            // Estado del ciclo de vida
            $table->enum('estado', ['pendiente', 'reconocida', 'escalada', 'vencida'])
                ->default('pendiente');

            // Vencimiento (solo para tipo alerta)
            $table->timestamp('expira_en')->nullable()
                ->comment('Calculado en horas laborales por HorarioLaboralService');

            // Escalada
            $table->timestamp('escalada_en')->nullable();
            $table->unsignedBigInteger('escalada_a_usuario_id')->nullable()
                ->comment('Supervisor que hereda la alerta tras vencimiento');

            $table->timestamps();

            // Índices
            $table->index(['origen_type', 'origen_id']);
            $table->index('destinatario_usuario_id');
            $table->index(['destinatario_uo_id', 'destinatario_rol']);
            $table->index('estado');

            // FKs
            $table->foreign('destinatario_usuario_id')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('destinatario_uo_id')
                ->references('id')->on('unidades_organizativas')->nullOnDelete();
            $table->foreign('escalada_a_usuario_id')
                ->references('id')->on('users')->nullOnDelete();
        });

        // Check constraint: integridad de destinatario
        \DB::statement("
            ALTER TABLE alertas
            ADD CONSTRAINT chk_alertas_destinatario CHECK (
                (destinatario_type = 'usuario' AND destinatario_usuario_id IS NOT NULL)
                OR
                (destinatario_type = 'rol_uo' AND destinatario_rol IS NOT NULL AND destinatario_uo_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};

<?php

use App\Services\AuditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de auditoría de accesos a datos de ciudadanos.
 *
 * Inmutable: no hay UPDATE ni DELETE en la capa de aplicación.
 * La única excepción es la purga por retención ejecutada por AuditPurgeCommand.
 *
 * @see docs/modulo-auditoria.md §2
 * @see AuditService
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion'); // AccionAuditEnum: ver|crear|editar|eliminar|exportar|imprimir|acceso_restringido
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('ciudadano_id')->nullable()->constrained('ciudadanos')->nullOnDelete();
            $table->jsonb('datos_antes')->nullable();
            $table->jsonb('datos_despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('contexto')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['ciudadano_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};

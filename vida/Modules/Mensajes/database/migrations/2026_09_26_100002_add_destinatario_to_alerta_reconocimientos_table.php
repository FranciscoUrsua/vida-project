<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte `alerta_reconocimientos` en el registro de eventos del
 * reconocimiento por destinatario.
 *
 * Se quita el índice único (alerta_id, usuario_id): un supervisor puede recibir
 * varias escaladas de la misma alerta, una por cada destinatario que no la
 * reconoció. Que nadie reconozca dos veces lo garantiza ahora el estado de
 * `alerta_destinatarios`.
 */
return new class extends Migration
{
    /**
     * Añade el vínculo al destinatario y retira el índice único.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('alerta_reconocimientos', function (Blueprint $table) {
            $table->dropUnique(['alerta_id', 'usuario_id']);
            $table->foreignId('alerta_destinatario_id')->nullable()->after('alerta_id')
                ->constrained('alerta_destinatarios')->cascadeOnDelete();
            $table->index(['alerta_id', 'usuario_id']);
        });
    }

    /**
     * Restaura la estructura anterior.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('alerta_reconocimientos', function (Blueprint $table) {
            $table->dropIndex(['alerta_id', 'usuario_id']);
            $table->dropConstrainedForeignId('alerta_destinatario_id');
            $table->unique(['alerta_id', 'usuario_id']);
        });
    }
};

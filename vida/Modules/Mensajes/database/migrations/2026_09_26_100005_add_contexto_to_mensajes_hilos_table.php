<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a `mensajes_hilos` el «elemento vinculado» del panel de redacción
 * (`modulo-mensajes.md` §4.3): el expediente, la ficha de valoración o el
 * plan desde el que se escribió el mensaje. Es un enlace de contexto, no un
 * adjunto: el contenido sigue en la Historia Social.
 */
return new class extends Migration
{
    /**
     * Añade las columnas.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('mensajes_hilos', function (Blueprint $table) {
            $table->string('contexto_tipo', 30)->nullable()->after('creado_por_id')
                ->comment('historia | ficha | plan (TipoContextoMensaje)');
            $table->unsignedBigInteger('contexto_id')->nullable()->after('contexto_tipo');
            $table->index(['contexto_tipo', 'contexto_id']);
        });
    }

    /**
     * Quita las columnas.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('mensajes_hilos', function (Blueprint $table) {
            $table->dropIndex(['contexto_tipo', 'contexto_id']);
            $table->dropColumn(['contexto_tipo', 'contexto_id']);
        });
    }
};

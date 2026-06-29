<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la FK de espacio_id en eventos_agenda y slots.
 *
 * Los eventos de agenda y los slots reservan Salas (salas de reuniones del
 * centro), no Espacios (habitaciones/módulos de plazas residenciales).
 * La FK anterior apuntaba erróneamente a la tabla espacios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_agenda', function (Blueprint $table) {
            $table->dropForeign(['espacio_id']);
            $table->foreign('espacio_id')->references('id')->on('salas')->nullOnDelete();
        });

        Schema::table('slots', function (Blueprint $table) {
            $table->dropForeign(['espacio_id']);
            $table->foreign('espacio_id')->references('id')->on('salas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('eventos_agenda', function (Blueprint $table) {
            $table->dropForeign(['espacio_id']);
            $table->foreign('espacio_id')->references('id')->on('espacios')->nullOnDelete();
        });

        Schema::table('slots', function (Blueprint $table) {
            $table->dropForeign(['espacio_id']);
            $table->foreign('espacio_id')->references('id')->on('espacios')->nullOnDelete();
        });
    }
};

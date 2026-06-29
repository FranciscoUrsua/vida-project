<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->dropForeign(['horario_centro_id']);
            $table->dropIndex(['horario_centro_id']);
            $table->dropIndex(['horario_centro_id', 'activo']);
            $table->dropColumn('horario_centro_id');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->foreignId('horario_centro_id')
                ->after('id')
                ->constrained('horarios_centro')
                ->cascadeOnDelete();
            $table->index('horario_centro_id');
            $table->index(['horario_centro_id', 'activo']);
        });
    }
};

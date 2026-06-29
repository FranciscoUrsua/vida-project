<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->boolean('bloquea_todos_convocados')->default(false)->after('genera_apunte_automatico');
        });
    }

    public function down(): void
    {
        Schema::table('tipos_slot', function (Blueprint $table) {
            $table->dropColumn('bloquea_todos_convocados');
        });
    }
};

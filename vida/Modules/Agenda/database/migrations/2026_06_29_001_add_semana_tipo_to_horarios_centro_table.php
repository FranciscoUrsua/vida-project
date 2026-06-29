<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->json('semana_tipo')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('horarios_centro', function (Blueprint $table) {
            $table->dropColumn('semana_tipo');
        });
    }
};

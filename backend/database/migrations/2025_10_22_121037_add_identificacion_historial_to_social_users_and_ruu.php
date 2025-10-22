<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('social_users', function (Blueprint $table) {
            $table->json('identificacion_historial')->nullable()->after('dni_nie_pasaporte');
        });

        Schema::table('ruu', function (Blueprint $table) {
            $table->json('identificacion_historial')->nullable()->after('dni_nie_pasaporte');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_users', function (Blueprint $table) {
            $table->dropColumn('identificacion_historial');
        });

        Schema::table('ruu', function (Blueprint $table) {
            $table->dropColumn('identificacion_historial');
        });
    }
};

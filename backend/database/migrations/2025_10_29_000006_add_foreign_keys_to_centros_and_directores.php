<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FK para centros -> directores
        Schema::table('centros', function (Blueprint $table) {
            $table->foreign('director_id')->references('id')->on('directores')->onDelete('set null');
        });

        // FKs para directores
        Schema::table('directores', function (Blueprint $table) {
            $table->foreign('profesional_id')->references('id')->on('profesionales')->onDelete('cascade');
            $table->foreign('centro_id')->references('id')->on('centros')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropForeign(['director_id']);
        });

        Schema::table('directores', function (Blueprint $table) {
            $table->dropForeign(['profesional_id']);
            $table->dropForeign(['centro_id']);
        });
    }
};

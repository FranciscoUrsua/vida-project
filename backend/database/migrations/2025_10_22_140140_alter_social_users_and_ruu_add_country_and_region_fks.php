<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Para social_users: Agrega FKs nuevas
        Schema::table('social_users', function (Blueprint $table) {
            $table->unsignedBigInteger('pais_origen_id')->nullable()->after('numero_tarjeta_sanitaria');  // Posición lógica
            $table->unsignedBigInteger('region_id')->nullable()->after('lugar_empadronamiento');

            $table->foreign('pais_origen_id')->references('id')->on('countries');
            $table->foreign('region_id')->references('id')->on('regions');
        });

        // Drop viejos SOLO si existen
        if (Schema::hasColumn('social_users', 'pais_origen')) {
            Schema::table('social_users', function (Blueprint $table) {
                $table->dropColumn('pais_origen');
            });
        }

        if (Schema::hasColumn('social_users', 'region')) {
            Schema::table('social_users', function (Blueprint $table) {
                $table->dropColumn('region');
            });
        }

        // Para ruu (mismos cambios)
        Schema::table('ruu', function (Blueprint $table) {
            $table->unsignedBigInteger('pais_origen_id')->nullable()->after('numero_tarjeta_sanitaria');
            $table->unsignedBigInteger('region_id')->nullable()->after('lugar_empadronamiento');

            $table->foreign('pais_origen_id')->references('id')->on('countries');
            $table->foreign('region_id')->references('id')->on('regions');
        });

        if (Schema::hasColumn('ruu', 'pais_origen')) {
            Schema::table('ruu', function (Blueprint $table) {
                $table->dropColumn('pais_origen');
            });
        }

        if (Schema::hasColumn('ruu', 'region')) {
            Schema::table('ruu', function (Blueprint $table) {
                $table->dropColumn('region');
            });
        }
    }

    public function down(): void
    {
        // Restaura columnas viejas (agrega si no existían)
        Schema::table('social_users', function (Blueprint $table) {
            $table->string('pais_origen')->nullable()->after('numero_tarjeta_sanitaria');
            $table->string('region')->nullable()->after('lugar_empadronamiento');
            $table->dropForeign(['pais_origen_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['pais_origen_id', 'region_id']);
        });

        Schema::table('ruu', function (Blueprint $table) {
            $table->string('pais_origen')->nullable()->after('numero_tarjeta_sanitaria');
            $table->string('region')->nullable()->after('lugar_empadronamiento');
            $table->dropForeign(['pais_origen_id']);
            $table->dropForeign(['region_id']);
            $table->dropColumn(['pais_origen_id', 'region_id']);
        });
    }
};

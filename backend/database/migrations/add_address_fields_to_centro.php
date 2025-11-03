<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Agrega solo si no existe
            if (!Schema::hasColumn('centros', 'street_type')) {
                $table->string('street_type', 50)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('centros', 'street_name')) {
                $table->string('street_name', 100)->nullable()->after('street_type');
            }
            if (!Schema::hasColumn('centros', 'street_number')) {
                $table->string('street_number', 20)->nullable()->after('street_name');
            }
            if (!Schema::hasColumn('centros', 'additional_info')) {
                $table->string('additional_info', 100)->nullable()->after('street_number');
            }
            if (!Schema::hasColumn('centros', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('additional_info');
            }
            if (!Schema::hasColumn('centros', 'city')) {
                $table->string('city', 100)->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('centros', 'lat')) {
                $table->decimal('lat', 10, 8)->nullable()->after('city');
            }
            if (!Schema::hasColumn('centros', 'lng')) {
                $table->decimal('lng', 11, 8)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('centros', 'direccion_validada')) {
                $table->boolean('direccion_validada')->default(false)->after('lng');
            }
            // Drop direccion_postal si existe
            if (Schema::hasColumn('centros', 'direccion_postal')) {
                $table->dropColumn('direccion_postal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Restore si needed
            if (!Schema::hasColumn('centros', 'direccion_postal')) {
                $table->text('direccion_postal')->after('nombre')->nullable();
            }
            $table->dropColumn(['street_type', 'street_name', 'street_number', 'additional_info', 'postal_code', 'city', 'lat', 'lng', 'direccion_validada']);
        });
    }
};

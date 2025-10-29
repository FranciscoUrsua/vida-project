<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_users', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->after('city');  // Lat
            $table->decimal('lng', 11, 8)->nullable()->after('lat');  // Lng
            $table->boolean('direccion_validada')->default(false)->after('lng');  // Flag
            $table->string('formatted_address')->nullable()->after('direccion_validada');  // Response OSM
        });
    }

    public function down(): void
    {
        Schema::table('social_users', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'direccion_validada', 'formatted_address']);
        });
    }
};

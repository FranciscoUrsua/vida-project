<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->after('direccion_postal');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->boolean('direccion_validada')->default(false)->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'direccion_validada']);
        });
    }
};

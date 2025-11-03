<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            // Drop campos_especificos
            if (Schema::hasColumn('centros', 'campos_especificos')) {
                $table->dropColumn('campos_especificos');
            }
            // Agrega referencia a tipo_centro
            $table->foreignId('tipo_centro_id')->nullable()->constrained('tipos_centro')->onDelete('set null')->after('nombre');
            // Agrega campos de dirección con defaults
            $table->string('city', 100)->default('Madrid')->change(); // Default si ya existe
            $table->string('pais', 50)->default('España')->after('city'); // Nuevo con default
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->json('campos_especificos')->after('nombre'); // Restore
            $table->dropForeign(['tipo_centro_id']);
            $table->dropColumn('tipo_centro_id');
            $table->string('pais')->nullable(); // Remove default
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->foreignId('unidad_convivencia_id')
                  ->nullable()
                  ->after('historia_id')
                  ->constrained('unidades_convivencia')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('planes_intervencion', function (Blueprint $table) {
            $table->dropForeignIdFor(\Modules\Ciudadania\Models\UnidadConvivencia::class);
            $table->dropColumn('unidad_convivencia_id');
        });
    }
};

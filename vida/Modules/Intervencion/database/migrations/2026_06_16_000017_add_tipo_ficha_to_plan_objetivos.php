<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->foreignId('tipo_ficha_id')
                  ->nullable()
                  ->after('nivel')
                  ->constrained('tipo_fichas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plan_objetivos', function (Blueprint $table) {
            $table->dropForeign(['tipo_ficha_id']);
            $table->dropColumn('tipo_ficha_id');
        });
    }
};

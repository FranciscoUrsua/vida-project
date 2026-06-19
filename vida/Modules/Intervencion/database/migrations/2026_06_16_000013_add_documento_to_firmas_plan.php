<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->foreignId('documento_firmado_id')
                ->nullable()
                ->after('fecha_firma')
                ->constrained('documentos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('firmas_plan', function (Blueprint $table) {
            $table->dropForeign(['documento_firmado_id']);
            $table->dropColumn('documento_firmado_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones_actividad', function (Blueprint $table) {
            $table->foreignId('sala_id')
                ->nullable()
                ->after('estado')
                ->constrained('salas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sesiones_actividad', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sala_id');
        });
    }
};

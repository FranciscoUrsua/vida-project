<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesional_id')->constrained('profesionales')->onDelete('cascade');
            $table->foreignId('centro_id')->constrained('centros')->onDelete('cascade');
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->unique(['profesional_id', 'centro_id']);
            $table->timestamps();
            $table->softDeletes();
        });

        // Romper ciclo: Agregar constraint a centros.director_id ahora que directores existe
        Schema::table('centros', function (Blueprint $table) {
            $table->foreign('director_id')->references('id')->on('directores')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Revertir constraint primero
        Schema::table('centros', function (Blueprint $table) {
            $table->dropForeign(['director_id']);
        });

        Schema::dropIfExists('directores');
    }
};

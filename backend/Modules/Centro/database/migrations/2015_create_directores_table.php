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
            $table->date('fecha_baja')->nullable(); // Permite directores actuales (sin baja)
            $table->timestamps();
            $table->softDeletes();
            // Constraints y índices
            $table->unique(['profesional_id', 'centro_id']); // Evita asignaciones duplicadas por centro-profesional
            $table->index(['fecha_alta', 'fecha_baja']); // Para queries históricas (e.g., directores activos en una fecha)
        });

        // Agregar FK inversa en centros.director_id (apunta al director actual/activo)
        Schema::table('centros', function (Blueprint $table) {
            $table->foreign('director_id')->references('id')->on('directores')->onDelete('set null');
            $table->index('director_id'); // Para joins eficientes
        });
    }

    public function down(): void
    {
        Schema::table('centros', function (Blueprint $table) {
            $table->dropForeign(['director_id']);
            $table->dropIndex(['director_id']); // Limpia el index agregado
        });
        Schema::dropIfExists('directores');
    }
};

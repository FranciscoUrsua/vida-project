<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_organizativas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 20)->unique(); // e.g., 'DEP-MAY' para departamento
            $table->string('nombre_corto', 100); // e.g., 'Departamento de Mayores'
            $table->text('descripcion')->nullable();
            $table->foreignUuid('parent_id')->nullable()->constrained('unidades_organizativas', 'id')->onDelete('cascade');
            $table->unsignedInteger('nivel')->default(1); // Auto-calculado
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'nivel']);
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_organizativas');
    }
};

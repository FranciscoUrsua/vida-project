<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_escalas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('codigo', 50)->unique();
            $table->text('descripcion')->nullable();
            $table->text('instrucciones_aplicacion')->nullable();
            $table->boolean('confirmar_instrucciones')->default(false);
            $table->text('fuente')->nullable();
            $table->jsonb('contextos')->default('[]');
            $table->jsonb('schema');
            $table->jsonb('rangos_interpretacion');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_escalas');
    }
};

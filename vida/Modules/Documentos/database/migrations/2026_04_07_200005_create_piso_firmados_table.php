<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piso_firmados', function (Blueprint $table) {
            $table->id();
            // FK sin constrained(): la tabla planes_de_intervencion aún no existe.
            // Se añadirá el constraint cuando el módulo Intervención implemente esa tabla.
            // La restricción unique garantiza que solo existe un PISO activo por plan.
            $table->unsignedBigInteger('plan_de_intervencion_id')->unique();
            $table->foreignId('documento_id')->constrained('documentos')->cascadeOnDelete();
            $table->foreignId('subido_por')->constrained('users');
            $table->string('metodo_conformidad_ciudadano');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('plan_de_intervencion_id');
            $table->index('documento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piso_firmados');
    }
};

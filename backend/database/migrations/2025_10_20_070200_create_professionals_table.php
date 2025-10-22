<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellidos');
            $table->string('entidad_adscripcion');
            $table->unsignedBigInteger('centro_unidad_id');  // FK a centros
            $table->enum('categoria_profesional', ['trabajador_social', 'psicologo', 'medico', 'administrativo', 'otro']);
            $table->enum('nivel_responsabilidad', ['basico', 'supervisor', 'director'])->default('basico');
            $table->json('perfil_acceso');  // Array de roles/permisos (integrar con Spatie)
            $table->timestamps();

            $table->foreign('centro_unidad_id')->references('id')->on('centros');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professionals');
    }
};

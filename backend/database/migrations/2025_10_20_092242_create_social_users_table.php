<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellidos');
            $table->string('dni_nie_pasaporte')->unique();
            $table->enum('situacion_administrativa', ['activa', 'inactiva', 'suspendida'])->default('activa');
            $table->string('numero_tarjeta_sanitaria')->nullable();
            $table->string('pais_origen')->default('Spain');
            $table->date('fecha_nacimiento');
            $table->enum('sexo', ['M', 'F', 'Other', 'Not specified'])->nullable();
            $table->enum('estado_civil', ['single', 'married', 'divorced', 'widowed', 'other'])->nullable();
            $table->string('lugar_empadronamiento');
            $table->string('correo')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('centro_adscripcion_id')->nullable();  // FK a centros
            $table->unsignedBigInteger('profesional_referencia_id')->nullable();  // FK a professionals
            $table->boolean('tiene_representante_legal')->default(false);
            $table->unsignedBigInteger('representante_legal_id')->nullable();  // FK a social_users o ruu
            $table->boolean('requiere_permiso_especial')->default(false);  // Para menores o VGG
            $table->timestamps();

            // FKs (agregar después de migrar dependencias)
            $table->foreign('centro_adscripcion_id')->references('id')->on('centros');
            $table->foreign('profesional_referencia_id')->references('id')->on('professionals');
            $table->foreign('representante_legal_id')->references('id')->on('social_users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_users');
    }
};

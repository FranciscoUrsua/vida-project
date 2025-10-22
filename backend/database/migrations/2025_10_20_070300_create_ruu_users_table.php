<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruu_users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellidos');
            $table->string('dni_nie_pasaporte')->unique();
            $table->enum('situacion_administrativa', ['activa', 'inactiva', 'suspendida'])->default('activa');
            $table->string('numero_tarjeta_sanitaria')->nullable();
            $table->string('pais_origen')->default('España');
            $table->date('fecha_nacimiento');
            $table->enum('sexo', ['M', 'F', 'Otro', 'No especificado'])->nullable();
            $table->enum('estado_civil', ['soltero', 'casado', 'divorciado', 'viudo', 'otro'])->nullable();
            $table->string('lugar_empadronamiento');
            $table->string('correo')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('centro_adscripcion_id')->nullable();  // FK a centros
            $table->unsignedBigInteger('profesional_referencia_id')->nullable();  // FK a profesionales
            $table->boolean('tiene_representante_legal')->default(false);
            $table->unsignedBigInteger('representante_legal_id')->nullable();  // FK a otro RUU si aplica
            $table->boolean('requiere_permiso_especial')->default(false);  // Para menores de edad (calcular de fecha_nacimiento)
            $table->timestamps();

            // FKs (agregar después de crear tablas relacionadas)
            $table->foreign('centro_adscripcion_id')->references('id')->on('centros');
            $table->foreign('profesional_referencia_id')->references('id')->on('professionals');
            $table->foreign('representante_legal_id')->references('id')->on('ruu_users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruu_users');
    }
};

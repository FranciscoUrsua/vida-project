<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_centro_id')->constrained('tipos_centros')->onDelete('restrict'); // Evita borrar tipos con centros
            $table->string('nombre', 255);
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            // Campos de georeferenciación (alineados con HasValidatableAddress trait)
            $table->string('street_type')->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_number')->nullable();
            $table->string('additional_info')->nullable();
            $table->string('postal_code')->nullable();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->onDelete('set null');
            $table->string('city')->default('Madrid');
            $table->string('country')->default('España');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->boolean('direccion_validada')->default(false);
            $table->string('formatted_address')->nullable();
            // Contacto
            $table->string('telefono', 20)->nullable();
            $table->string('email_contacto', 255)->nullable(); // Sin unique, permite compartidos
            // Personal y director (versionables via trait)
            $table->foreignId('director_id')->nullable()->constrained('directores')->onDelete('set null');
            $table->json('personal')->nullable(); // Array de IDs de app_users, e.g., [1, 2]
            // Datos dinámicos por tipo
            $table->json('datos_especificos')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // Índices para rendimiento (búsquedas geo y por nombre)
            $table->index(['distrito_id', 'postal_code']);
            $table->index('nombre');
            $table->index('tipo_centro_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};

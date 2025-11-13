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
            $table->string('first_name');
            $table->string('last_name1');
            $table->string('last_name2')->nullable();
            $table->enum('situacion_administrativa', ['activa', 'inactiva', 'suspendida'])->default('activa')->nullable();
            $table->string('numero_tarjeta_sanitaria')->nullable();
            $table->foreignId('pais_origen_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->foreignId('region_id')->nullable()->constrained('regions')->onDelete('set null');
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('sexo', ['M', 'F', 'D'])->nullable();
            $table->enum('estado_civil', ['single', 'married', 'divorced', 'widowed', 'other'])->nullable();
            $table->string('lugar_empadronamiento')->nullable();
            // Georeferenciación split
            $table->string('street_type')->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_number')->nullable();
            $table->string('additional_info')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->onDelete('set null');
            $table->string('city')->default('Madrid');
            $table->string('country')->default('España');
            $table->string('correo')->unique()->nullable();
            $table->string('telefono')->nullable();
            $table->foreignId('centro_adscripcion_id')->nullable()->constrained('centros')->onDelete('set null');
            $table->foreignId('profesional_referencia_id')->nullable()->constrained('profesionales')->onDelete('set null');
            $table->boolean('tiene_representante_legal')->default(false)->nullable();
            $table->foreignId('representante_legal_id')->nullable()->constrained('social_users')->onDelete('set null');
            $table->boolean('requiere_permiso_especial')->default(false)->nullable();
            $table->boolean('identificacion_desconocida')->default(false);
            $table->json('identificacion_historial')->nullable();
            $table->enum('tipo_documento', ['dni', 'nie', 'pasaporte', 'otro'])->nullable();
            $table->string('numero_id')->nullable()->unique();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->boolean('direccion_validada')->default(false);
            $table->string('formatted_address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_users');
    }
};

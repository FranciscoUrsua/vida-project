<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profesionales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido1', 100);
            $table->string('apellido2', 100)->nullable();
            $table->enum('tipo_documento', ['DNI', 'NIE', 'PASAPORTE', 'OTRO'])->nullable(); // CAMBIADO: tipo_documento enum
            $table->string('numero_id', 20)->unique();
            $table->string('email', 255)->unique();
            $table->string('telefono', 20)->nullable();
            $table->enum('sexo', ['M', 'F', 'D'])->nullable();
            $table->boolean('identificacion_validada')->default(false); // Flag de validación
            $table->json('identificacion_historial')->nullable();
            $table->foreignId('titulacion_id')->nullable()->constrained('titulaciones')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};

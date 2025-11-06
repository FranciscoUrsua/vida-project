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
            $table->string('tipo_id', 10);
            $table->string('numero_id', 20)->unique();
            $table->string('email', 255)->unique();
            $table->string('telefono', 20)->nullable();
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

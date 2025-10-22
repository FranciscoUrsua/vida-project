<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsu_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hsu_id');
            $table->date('fecha');
            $table->enum('tipo_evento', ['evaluacion', 'intervencion', 'cambio', 'incidente']);
            $table->text('descripcion');
            $table->json('datos_adjuntos')->nullable();  // Archivos, notas, etc.
            $table->unsignedBigInteger('auditor_id')->nullable();  // FK a User/Professional
            $table->timestamps();

            $table->foreign('hsu_id')->references('id')->on('hsu');
            $table->foreign('auditor_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsu_entries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('centro_id');
            $table->string('nombre');
            $table->text('descripcion');
            $table->enum('categoria', ['atencion_primaria', 'residencial', 'especializada']);
            $table->timestamps();

            $table->foreign('centro_id')->references('id')->on('centros')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};

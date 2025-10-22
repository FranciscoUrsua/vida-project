<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // ej: 'Comunidad de Madrid'
            $table->string('code', 2)->unique();  // Código autonómico, ej: 'MD'
            $table->unsignedBigInteger('country_id');  // FK a countries (España por defecto)
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};

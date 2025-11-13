<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calles_numeros', function (Blueprint $table) {
            $table->id();
            $table->string('street_name');
            $table->string('street_number');
            $table->string('postal_code', 5);
            $table->string('distrito_nombre');
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->string('formatted_address');
            $table->timestamps();
            $table->index(['street_name', 'street_number', 'postal_code']); // Para queries rápidas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calles_numeros');
    }
};

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
            $table->string('tipo', 50);
            $table->string('nombre', 255);
            // Georeferenciación split
            $table->string('street_type')->nullable();
            $table->string('street_name')->nullable();
            $table->string('street_number')->nullable();
            $table->string('additional_info')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->foreignId('distrito_id')->nullable()->constrained('distritos')->onDelete('set null');
            $table->string('city')->default('Madrid');
            $table->string('country')->default('España');
            $table->string('telefono', 20);
            $table->string('email_contacto', 255)->nullable();
            $table->unsignedBigInteger('director_id')->nullable()->index();
            $table->json('campos_especificos')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->boolean('direccion_validada')->default(false);
            $table->string('formatted_address')->nullable(); // AGREGADO: Para address formatted de API
            $table->timestamps();
            $table->softDeletes();
            $table->index(['distrito_id', 'postal_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros');
    }
};

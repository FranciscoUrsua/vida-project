<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ruu_user_id');  // FK a RUU
            $table->json('situacion_actual');  // {salud: 'estable', economia: 'baja', familia: 'desestructurada'}
            $table->boolean('requiere_permiso_especial')->default(false);  // Para episodios de violencia de género
            $table->timestamps();

            $table->foreign('ruu_user_id')->references('id')->on('ruu_users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsu');
    }
};

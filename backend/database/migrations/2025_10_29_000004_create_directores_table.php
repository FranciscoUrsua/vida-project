<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profesional_id'); // Sin constrained aún
            $table->unsignedBigInteger('centro_id'); // Sin constrained aún
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->unique(['profesional_id', 'centro_id']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directores');
    }
};

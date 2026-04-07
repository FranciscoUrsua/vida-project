<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_usuario', function (Blueprint $table) {
            $table->foreignId('evento_agenda_id')->constrained('eventos_agenda')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('confirmado')->default(false);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->primary(['evento_agenda_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_usuario');
    }
};

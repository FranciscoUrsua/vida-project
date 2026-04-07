<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->string('tipo_evento');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->foreignId('espacio_id')->nullable()->constrained('espacios')->nullOnDelete();
            $table->foreignId('creado_por_id')->constrained('users')->cascadeOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('centro_id');
            $table->index(['centro_id', 'fecha']);
            $table->index('creado_por_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_agenda');
    }
};

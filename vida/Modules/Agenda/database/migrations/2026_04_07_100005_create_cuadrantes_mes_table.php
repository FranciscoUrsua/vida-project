<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuadrantes_mes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros')->cascadeOnDelete();
            $table->unsignedSmallInteger('anyo');
            $table->unsignedTinyInteger('mes');
            $table->string('estado')->default('borrador');
            $table->boolean('generado_con_ia')->default(false);
            $table->boolean('generado_automaticamente')->default(false);
            $table->timestamp('publicado_en')->nullable();
            $table->foreignId('publicado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('centro_id');
            $table->unique(['centro_id', 'anyo', 'mes']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadrantes_mes');
    }
};

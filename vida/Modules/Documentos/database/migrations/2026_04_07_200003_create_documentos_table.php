<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('tipo_documento_id')->constrained('catalogos_sistema');
            $table->string('origen');
            $table->string('nombre_original');
            $table->string('ruta_almacenamiento');
            $table->string('disco');
            $table->string('mime_type');
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('hash_sha256');
            $table->foreignId('subido_por')->constrained('users');
            $table->text('descripcion')->nullable();
            $table->timestamps();

            // Nota: morphs() ya crea el índice compuesto sobre documentable_type y documentable_id
            $table->index('subido_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('informes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_informe');
            $table->foreignId('historia_social_id')
                ->nullable()
                ->constrained('historias_sociales')
                ->nullOnDelete();
            $table->foreignId('ciudadano_id')->constrained('ciudadanos');
            $table->foreignId('autor_id')->constrained('users');
            $table->string('estado')->default('borrador');
            $table->jsonb('contenido');
            $table->foreignId('documento_id')
                ->nullable()
                ->constrained('documentos')
                ->nullOnDelete();
            $table->timestamp('firmado_en')->nullable();
            $table->string('metodo_firma')->nullable();
            $table->string('numero_colegiado_firmante')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index('autor_id');
            $table->index(['autor_id', 'estado']);
            $table->index('ciudadano_id');
            $table->index('historia_social_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informes');
    }
};

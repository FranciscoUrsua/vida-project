<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Documentos\Enums\EstadoPropuestaEliminacion;

/**
 * Propuestas de destrucción de versiones con el plazo de conservación vencido (paso 5).
 *
 * `documentos:proponer-destruccion` las genera; nada se destruye hasta que un
 * adm_sistema aprueba la propuesta, y la aprobación levanta el acta de eliminación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propuestas_eliminacion', function (Blueprint $table) {
            $table->id();
            $table->enum('estado', EstadoPropuestaEliminacion::valores())->default(EstadoPropuestaEliminacion::Pendiente->value);
            $table->jsonb('versiones')->comment('Ids de documento_versiones propuestas');
            $table->jsonb('excluidas')->nullable()->comment('Ids excluidos al aprobar (retenidas o ya sin contenido)');
            $table->foreignId('resuelta_por')->nullable()->constrained('users');
            $table->timestamp('resuelta_en')->nullable();
            $table->foreignId('acta_eliminacion_id')->nullable()->constrained('actas_eliminacion');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propuestas_eliminacion');
    }
};

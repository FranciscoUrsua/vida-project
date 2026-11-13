<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denominaciones con vigencia de tipos de centro y centros (RN-02, RN-12).
 *
 * Un renombrado cierra la denominación vigente y abre otra: la identidad del
 * tipo o del centro no cambia y siempre se puede saber cómo se llamaba a fecha X.
 */
return new class extends Migration
{
    /**
     * Crea la tabla polimórfica `denominaciones`.
     */
    public function up(): void
    {
        Schema::create('denominaciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('denominable');
            $table->string('nombre', 200);
            $table->string('nombre_corto', 50)->comment('Siglas del tipo o nombre corto del centro');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['denominable_type', 'denominable_id', 'vigente_desde']);
        });
    }

    /**
     * Elimina la tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('denominaciones');
    }
};

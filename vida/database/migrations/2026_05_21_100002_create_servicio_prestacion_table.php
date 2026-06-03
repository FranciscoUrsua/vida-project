<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Centro\Models\Servicio;

/**
 * Relación N:M entre servicios y prestaciones del catálogo.
 *
 * Un servicio tramita una o más prestaciones; una prestación puede ser
 * tramitada por varios servicios. Esta tabla es la razón de ser del servicio.
 *
 * @see Servicio
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_prestacion', function (Blueprint $table) {
            $table->foreignId('servicio_id')
                ->constrained('servicios')
                ->cascadeOnDelete();

            $table->foreignId('prestacion_id')
                ->constrained('prestaciones')
                ->restrictOnDelete();

            $table->primary(['servicio_id', 'prestacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_prestacion');
    }
};

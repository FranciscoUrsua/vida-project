<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Retira `solicitudes_servicio` (fase 3 bis, paso 6): la sustituye la solicitud de prestación
 * con vía de tramitación interna. Sin registros fuera de tests (comprobado el 15 nov 2026).
 * `servicios` se mantiene: su UO puede ser unidad tramitadora de una prestación.
 */
return new class extends Migration
{
    /**
     * Elimina la tabla.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::dropIfExists('solicitudes_servicio');
    }

    /**
     * Vuelve a crearla con la migración original.
     *
     * @return void
     */
    public function down(): void
    {
        (require database_path('migrations/2026_05_21_100005_create_solicitudes_servicio_table.php'))->up();
    }
};

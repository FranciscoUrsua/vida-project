<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El tipo de cita genérico «cita» solo se da en slots de Entrevista (decisión del
 * desarrollador, 2026-11-16): sin enlaces admitía cualquier tipo de slot activo, también
 * Descanso, Trabajo propio o Mesa de trabajo. Se enlaza a Entrevista si ambos existen y el
 * genérico no tenía enlaces; se puede cambiar desde TipoCitaResource.
 */
return new class extends Migration
{
    /**
     * Enlaza el tipo genérico con el tipo de slot Entrevista.
     *
     * @return void
     */
    public function up(): void
    {
        $generico = DB::table('tipos_cita')->where('codigo', 'cita')->whereNull('deleted_at')->value('id');
        $entrevista = DB::table('tipos_slot')->where('nombre', 'Entrevista')->value('id');

        if ($generico === null || $entrevista === null) {
            return;
        }

        if (DB::table('tipo_cita_tipo_slot')->where('tipo_cita_id', $generico)->exists()) {
            return;
        }

        DB::table('tipo_cita_tipo_slot')->insert([
            'tipo_cita_id' => $generico,
            'tipo_slot_id' => $entrevista,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sin vuelta atrás automática: el enlace puede haberse editado en el panel.
     *
     * @return void
     */
    public function down(): void {}
};

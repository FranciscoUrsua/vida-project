<?php

namespace Modules\Agenda\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Agenda\Enums\ModoAgenda;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\TipoSlot;

/**
 * Seeder del módulo Agenda.
 *
 * Crea un HorarioCentro de ejemplo y tres TipoSlot básicos para el primer
 * centro disponible en la base de datos. No crea cuadrantes, slots ni citas,
 * ya que esos datos requieren usuarios y ciudadanos reales.
 *
 * Ejecución directa:
 *   php artisan db:seed --class=Modules\\Agenda\\Database\\Seeders\\AgendaSeeder
 */
class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        $centro = DB::table('centros')->first();

        if (! $centro) {
            $this->command->warn('AgendaSeeder: no hay centros en la BD. Omitiendo seeder.');

            return;
        }

        $horario = HorarioCentro::firstOrCreate(
            ['centro_id' => $centro->id, 'nombre' => 'Horario general'],
            [
                'dias_laborables' => [1, 2, 3, 4, 5],
                'hora_apertura' => '09:00',
                'hora_cierre' => '19:00',
                'hora_inicio_atencion' => '09:00',
                'hora_fin_atencion' => '19:00',
                'buffer_inicio_minutos' => 0,
                'buffer_fin_minutos' => 0,
                'vigente_desde' => now()->toDateString(),
                'vigente_hasta' => null,
                'modo_agenda' => ModoAgenda::Estandar->value,
                'activo' => true,
                'notas' => null,
            ]
        );

        $tipos = [
            [
                'nombre' => 'Entrevista TSR',
                'descripcion' => 'Entrevista con el Trabajador Social de Referencia.',
                'duracion_minutos' => 45,
                'requiere_espacio' => false,
                'porcentaje_urgencias' => 20,
                'origen_permitido' => OrigenPermitidoSlot::Ambos->value,
                'genera_apunte_automatico' => false,
                'activo' => true,
            ],
            [
                'nombre' => 'Primera atención SIA',
                'descripcion' => 'Primera atención en el Servicio de Información y Asesoramiento.',
                'duracion_minutos' => 30,
                'requiere_espacio' => false,
                'porcentaje_urgencias' => 30,
                'origen_permitido' => OrigenPermitidoSlot::Ambos->value,
                'genera_apunte_automatico' => false,
                'activo' => true,
            ],
            [
                'nombre' => 'Reunión de coordinación',
                'descripcion' => 'Reunión interna de coordinación de equipo o con entidades externas.',
                'duracion_minutos' => 60,
                'requiere_espacio' => true,
                'porcentaje_urgencias' => 0,
                'origen_permitido' => OrigenPermitidoSlot::Interno->value,
                'genera_apunte_automatico' => false,
                'activo' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoSlot::firstOrCreate(
                ['horario_centro_id' => $horario->id, 'nombre' => $tipo['nombre']],
                $tipo
            );
        }

        // Crear valor en catalogos_sistema para tipo_evento_agenda si la tabla existe
        if (Schema::hasTable('catalogos_sistema')) {
            $existe = DB::table('catalogos_sistema')
                ->where('grupo', 'tipo_evento_agenda')
                ->exists();

            if (! $existe) {
                DB::table('catalogos_sistema')->insert([
                    'grupo' => 'tipo_evento_agenda',
                    'clave' => 'reunion_equipo',
                    'etiqueta' => 'Reunión de equipo',
                    'orden' => 1,
                    'activo' => true,
                ]);
            }
        }

        $this->command->info("AgendaSeeder: horario y tipos de slot creados para el centro #{$centro->id}.");
    }
}

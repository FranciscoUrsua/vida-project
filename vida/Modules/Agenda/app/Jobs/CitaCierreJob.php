<?php

namespace Modules\Agenda\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\Agenda\Enums\AccionCitaEvento;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\AvisosCitas;
use Modules\Agenda\Services\Citas\RegistroEventosCita;

/**
 * Cierre del día laboral de las citas (docs/modulo-citas.md §3.6 y §3.1.4).
 *
 * - Marca como pendientes de cierre las citas confirmadas cuya hora pasó sin
 *   apunte, registro de atención ni incomparecencia, y avisa a su profesional.
 * - Avisa a supervisión, una sola vez por cita, de las que siguen pendientes
 *   más días laborables de los que fija el centro.
 * - Avisa a supervisión, una sola vez por solicitud, de las solicitudes
 *   abiertas cuya fecha límite pasó.
 *
 * Nunca cambia el estado de una cita: el sistema no cierra citas por su cuenta.
 * Se programa antes que SlotExpirationJob (routes/console.php).
 */
class CitaCierreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Ejecuta las tres comprobaciones.
     *
     * @param RegistroEventosCita $eventos
     * @param AvisosCitas $avisos
     * @return void
     */
    public function handle(RegistroEventosCita $eventos, AvisosCitas $avisos): void
    {
        $this->marcarPendientes($eventos, $avisos);
        $this->avisarPendientesProlongadas($avisos);
        $this->avisarSolicitudesFueraDePlazo($avisos);
    }

    /**
     * @param RegistroEventosCita $eventos
     * @param AvisosCitas $avisos
     * @return void
     */
    private function marcarPendientes(RegistroEventosCita $eventos, AvisosCitas $avisos): void
    {
        $ahora = now();

        Cita::where('estado', EstadoCita::Confirmada)
            ->where('pendiente_cierre', false)
            ->whereNotNull('ciudadano_id')
            ->where(fn ($q) => $q->where('fecha', '<', $ahora->toDateString())
                ->orWhere(fn ($hoy) => $hoy->where('fecha', $ahora->toDateString())->where('hora_fin', '<=', $ahora->format('H:i:s'))))
            ->whereDoesntHave('apuntes')
            ->whereDoesntHave('registrosAtencion')
            ->with('centro')
            ->each(function (Cita $cita) use ($eventos, $avisos) {
                DB::transaction(function () use ($cita, $eventos, $avisos) {
                    $cita->update(['pendiente_cierre' => true, 'pendiente_cierre_desde' => now()]);
                    $eventos->registrar(AccionCitaEvento::MarcadaPendienteCierre, cita: $cita, datos: RegistroEventosCita::datosSlot($cita));
                    $avisos->citaPendienteCierre($cita);
                });
            });
    }

    /**
     * @param AvisosCitas $avisos
     * @return void
     */
    private function avisarPendientesProlongadas(AvisosCitas $avisos): void
    {
        Cita::where('estado', EstadoCita::Confirmada)
            ->where('pendiente_cierre', true)
            ->whereNull('aviso_cierre_supervisor_en')
            ->with('centro')
            ->each(function (Cita $cita) use ($avisos) {
                $horario = HorarioCentro::vigenteDelCentro($cita->centro_id);
                $limite = $horario?->dias_aviso_cierre_supervisor ?? 3;
                $dias = $horario?->diasLaborablesEntre($cita->pendiente_cierre_desde, now()) ?? $cita->pendiente_cierre_desde->diffInWeekdays(now());

                if ($dias > $limite) {
                    DB::transaction(function () use ($cita, $avisos) {
                        $cita->update(['aviso_cierre_supervisor_en' => now()]);
                        $avisos->citaPendienteCierreProlongada($cita);
                    });
                }
            });
    }

    /**
     * @param AvisosCitas $avisos
     * @return void
     */
    private function avisarSolicitudesFueraDePlazo(AvisosCitas $avisos): void
    {
        SolicitudCita::abiertas()
            ->whereDate('no_despues_de', '<', today())
            ->whereNull('aviso_fuera_plazo_en')
            ->with(['centro', 'tipoCita'])
            ->each(function (SolicitudCita $solicitud) use ($avisos) {
                DB::transaction(function () use ($solicitud, $avisos) {
                    $solicitud->update(['aviso_fuera_plazo_en' => now()]);
                    $avisos->solicitudFueraDePlazo($solicitud);
                });
            });
    }
}

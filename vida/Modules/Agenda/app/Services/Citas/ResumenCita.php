<?php

namespace Modules\Agenda\Services\Citas;

use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenCita;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Intervencion\Models\Apunte;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;

/**
 * Datos de la sección *Cita* o *Coordinación* del detalle de un apunte en el
 * timeline (docs/modulo-citas.md §5). El resumen de la tarjeta no cambia: esto
 * solo se pinta al desplegarla. Incomparecencias y cancelaciones no llegan al
 * timeline porque no tienen apunte.
 */
class ResumenCita
{
    /**
     * @param AsignacionReferenciaService $referencias
     */
    public function __construct(private readonly AsignacionReferenciaService $referencias) {}

    /**
     * Sección de la cita que atiende el apunte, o null si no tiene.
     *
     * @param Apunte $apunte
     * @return array{origen: string, solicitada_por: string|null, solicitada_en: string|null, prevista: string, real: string, demora_dias: int|null, modo: string, referencia: string|null, reprogramaciones: int, cita_id: int, acompanantes: list<string>}|null
     */
    public function deApunte(Apunte $apunte): ?array
    {
        $cita = $apunte->cita_id ? Cita::with(['solicitud.solicitante', 'acompanantes.ciudadano', 'profesional'])->find($apunte->cita_id) : null;

        if ($cita === null) {
            return null;
        }

        $cadena = $cita->cadenaReprogramaciones();
        $primera = $cadena->first();
        $solicitud = $cita->solicitud;

        return [
            'origen' => $cita->origen === OrigenCita::ApiExterna ? 'Cita previa' : ($solicitud?->canal->label() ?? 'Interno'),
            'solicitada_por' => $solicitud?->solicitante?->nombre_completo,
            'solicitada_en' => $solicitud?->created_at?->format('d/m/Y'),
            'prevista' => $primera->fecha->format('d/m/Y').' '.substr((string) $primera->hora_inicio, 0, 5),
            'real' => $cita->fecha->format('d/m/Y').' '.substr((string) $cita->hora_inicio, 0, 5),
            'demora_dias' => $solicitud ? (int) $solicitud->created_at->copy()->startOfDay()->diffInDays($cita->fecha) : null,
            'modo' => $cita->modo_asignacion->label(),
            'referencia' => $cita->modo_asignacion === ModoAsignacionCita::Sustituto
                ? $this->referencias->vigente($apunte->historia()->withoutGlobalScopes()->first())?->profesional?->nombre_completo
                : null,
            'reprogramaciones' => $cadena->count() - 1,
            'cita_id' => $cita->id,
            'acompanantes' => $cita->acompanantes->map(fn ($a) => $a->nombreVisible())->values()->all(),
        ];
    }

    /**
     * Sección del evento de agenda del que nace el apunte, o null.
     *
     * @param Apunte $apunte
     * @return array{titulo: string, tipo: string, fecha: string, convocados: list<string>}|null
     */
    public function coordinacion(Apunte $apunte): ?array
    {
        $evento = $apunte->evento_agenda_id ? EventoAgenda::with('profesionales.profesional')->find($apunte->evento_agenda_id) : null;

        if ($evento === null) {
            return null;
        }

        return [
            'titulo' => $evento->titulo,
            'tipo' => (string) $evento->tipo_evento,
            'fecha' => $evento->fecha->format('d/m/Y').' '.substr((string) $evento->hora_inicio, 0, 5),
            'convocados' => $evento->profesionales->map(fn ($u) => $u->nombre_completo)->values()->all(),
        ];
    }
}

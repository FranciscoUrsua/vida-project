<?php

namespace Modules\Agenda\Services\Citas;

use App\Models\HistoriaSocial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Agenda\Enums\DestinoCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Enums\OrigenPermitidoSlot;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Models\TipoSlot;
use Modules\Agenda\Services\DisponibilidadService;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;

/**
 * Propone huecos para una solicitud de cita (docs/modulo-citas.md §3.3).
 *
 * Solo slots del centro, de tipos compatibles con el tipo de cita y admitidos
 * en el canal interno, dentro de la ventana de la solicitud y sin empezar.
 * Las urgentes pueden consumir slots reservados para urgencias; el resto no.
 * Según el destino:
 * - profesional concreto: solo ese profesional;
 * - referencia: solo la referencia; si no tiene hueco en la ventana o una
 *   ausencia que cubre más días de la ventana que el umbral del centro, otros
 *   profesionales de su mismo perfil como sustitutos (la referencia no cambia);
 * - servicio o primer libre: los profesionales del perfil (o todos), por fecha.
 *
 * No cita: el sistema propone y la persona elige. Se apoya en DisponibilidadService.
 */
class BusquedaHuecosService
{
    /**
     * @param DisponibilidadService $disponibilidad
     * @param AsignacionReferenciaService $referencias
     */
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
        private readonly AsignacionReferenciaService $referencias,
    ) {}

    /**
     * Huecos propuestos, ordenados por fecha y hora.
     *
     * @param SolicitudCita $solicitud
     * @param int $limite
     * @return Collection<int, PropuestaHueco>
     */
    public function buscar(SolicitudCita $solicitud, int $limite = 10): Collection
    {
        [$desde, $hasta] = $this->ventana($solicitud);

        if ($desde->gt($hasta)) {
            return collect();
        }

        $tipos = TipoSlot::whereKey($solicitud->tipoCita->idsTiposSlotCompatibles())
            ->whereIn('origen_permitido', [OrigenPermitidoSlot::Interno->value, OrigenPermitidoSlot::Ambos->value])
            ->pluck('id')
            ->all();

        if ($tipos === []) {
            return collect();
        }

        $buscar = fn (?array $usuarios) => $this->libres($solicitud, $usuarios, $tipos, $desde, $hasta);

        $propuestas = match ($solicitud->destino) {
            DestinoCita::ProfesionalConcreto => $this->propuestas($buscar([$solicitud->profesional_destino_id]), ModoAsignacionCita::ProfesionalConcreto),
            DestinoCita::Referencia => $this->paraReferencia($solicitud, $buscar, $desde, $hasta),
            DestinoCita::Servicio, DestinoCita::PrimerLibre => $this->propuestas(
                $buscar($solicitud->servicio_destino ? $this->usuariosDelPerfil($solicitud->servicio_destino) : null),
                ModoAsignacionCita::PrimerLibre,
            ),
        };

        return $propuestas->take($limite)->values();
    }

    /**
     * Destino referencia: la referencia o, si no está disponible, sustitutos de su perfil.
     *
     * @param SolicitudCita $solicitud
     * @param callable(list<int>|null): Collection<int, Slot> $buscar
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return Collection<int, PropuestaHueco>
     */
    private function paraReferencia(SolicitudCita $solicitud, callable $buscar, Carbon $desde, Carbon $hasta): Collection
    {
        $historia = HistoriaSocial::withoutGlobalScopes()->where('ciudadano_id', $solicitud->ciudadano_id)->first();
        $referencia = $historia ? $this->referencias->vigente($historia)?->profesional : null;

        if ($referencia === null) {
            return collect();
        }

        $suyos = $buscar([$referencia->id]);

        if ($suyos->isNotEmpty() && ! $this->ausenciaProlongada($referencia, $solicitud->centro_id, $desde, $hasta)) {
            return $this->propuestas($suyos, ModoAsignacionCita::Referencia);
        }

        // Sustitutos: mismo perfil (cargo), nunca otro; la referencia de la persona no cambia
        $slug = $referencia->profesional?->cargo?->slug;

        if ($slug === null) {
            return collect();
        }

        $sustitutos = array_values(array_diff($this->usuariosDelPerfil($slug), [$referencia->id]));

        return $sustitutos === [] ? collect() : $this->propuestas($buscar($sustitutos), ModoAsignacionCita::Sustituto);
    }

    /**
     * Si las ausencias del profesional cubren más días de la ventana que el umbral del centro.
     *
     * @param User $profesional
     * @param int $centroId
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return bool
     */
    private function ausenciaProlongada(User $profesional, int $centroId, Carbon $desde, Carbon $hasta): bool
    {
        $umbral = HorarioCentro::vigenteDelCentro($centroId, $desde)?->dias_ausencia_prolongada ?? 15;

        $dias = ExcepcionProfesional::where('usuario_id', $profesional->id)
            ->where('afecta_disponibilidad', true)
            ->whereDate('fecha_inicio', '<=', $hasta->toDateString())
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $desde->toDateString()))
            ->get(['fecha_inicio', 'fecha_fin'])
            ->flatMap(function (ExcepcionProfesional $e) use ($desde, $hasta) {
                $inicio = Carbon::parse($e->fecha_inicio)->max($desde);
                $fin = $e->fecha_fin ? Carbon::parse($e->fecha_fin)->min($hasta) : $hasta->copy();

                return collect(range(0, max(0, (int) $inicio->diffInDays($fin))))->map(fn (int $i) => $inicio->copy()->addDays($i)->toDateString());
            })
            ->unique()
            ->count();

        return $dias > $umbral;
    }

    /**
     * Slots libres (sin empezar) de los profesionales dados.
     *
     * @param SolicitudCita $solicitud
     * @param list<int>|null $usuarios Null: todos los del centro.
     * @param list<int> $tipos
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return Collection<int, Slot>
     */
    private function libres(SolicitudCita $solicitud, ?array $usuarios, array $tipos, Carbon $desde, Carbon $hasta): Collection
    {
        $ahora = now();

        return $this->disponibilidad
            ->obtenerSlotsDe($usuarios, $solicitud->centro_id, $tipos, $desde, $hasta, $solicitud->urgencia->admiteSlotsUrgencia())
            ->reject(fn (Slot $slot) => $slot->fecha->isSameDay($ahora) && substr((string) $slot->hora_inicio, 0, 5) <= $ahora->format('H:i'))
            ->values();
    }

    /**
     * Convierte slots en propuestas con su profesional.
     *
     * @param Collection<int, Slot> $slots
     * @param ModoAsignacionCita $modo
     * @return Collection<int, PropuestaHueco>
     */
    private function propuestas(Collection $slots, ModoAsignacionCita $modo): Collection
    {
        $usuarios = User::with('profesional')->whereIn('id', $slots->pluck('usuario_id')->unique())->get()->keyBy('id');

        return $slots
            ->filter(fn (Slot $slot) => $usuarios->has($slot->usuario_id))
            ->map(fn (Slot $slot) => new PropuestaHueco($slot, $usuarios[$slot->usuario_id], $modo))
            ->values();
    }

    /**
     * Usuarios cuyo profesional tiene el cargo (perfil) dado.
     *
     * @param string $slug
     * @return list<int>
     */
    private function usuariosDelPerfil(string $slug): array
    {
        return User::whereHas('profesional.cargo', fn ($q) => $q->where('slug', $slug))->pluck('id')->all();
    }

    /**
     * Ventana de búsqueda: desde hoy (o no antes de) hasta la fecha límite.
     *
     * @param SolicitudCita $solicitud
     * @return array{0: Carbon, 1: Carbon}
     */
    private function ventana(SolicitudCita $solicitud): array
    {
        $desde = today();

        if ($solicitud->no_antes_de !== null && $solicitud->no_antes_de->gt($desde)) {
            $desde = $solicitud->no_antes_de->copy();
        }

        return [$desde, $solicitud->no_despues_de->copy()];
    }
}

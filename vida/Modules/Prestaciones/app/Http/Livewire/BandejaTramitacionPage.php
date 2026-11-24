<?php

namespace Modules\Prestaciones\Http\Livewire;

use App\Livewire\Concerns\OrdenaPorColumnas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Prestaciones\Enums\EstadoSolicitudPrestacion;
use Modules\Prestaciones\Models\Prestacion;
use Modules\Prestaciones\Models\SolicitudPrestacion;

/**
 * Bandeja de tramitación (fase 3 bis, paso 5.3; RN-02 y RN-03).
 *
 * Solicitudes de las unidades del usuario (sus adscripciones y descendientes orgánicas):
 * pendientes de tomar, en preparación (mías o todas), enviadas con incidencia abierta y
 * enviadas. Filtros por prestación y antigüedad; ordenable por cabecera (por defecto, el
 * último movimiento más antiguo primero: es una cola). Tramitación ve la identidad de las personas
 * de su bandeja, también protegidas, porque la necesita para tramitar (D3.8); cada apertura de
 * una solicitud de persona protegida se audita en la pantalla de la solicitud.
 *
 * @property-read Collection<int, SolicitudPrestacion> $solicitudes
 * @property-read array<string, int> $contadores
 */
class BandejaTramitacionPage extends Component
{
    use OrdenaPorColumnas;

    /** Pestaña: pendientes, preparacion, incidencias, enviadas. */
    #[Url]
    public string $pestana = 'pendientes';

    /** En preparación: solo las mías. */
    #[Url]
    public bool $soloMias = true;

    #[Url]
    public ?int $prestacionId = null;

    /** Antigüedad mínima en días (0 = todas). */
    #[Url]
    public int $antiguedad = 0;

    /**
     * Solicitudes de la pestaña y los filtros.
     *
     * @return Collection<int, SolicitudPrestacion>
     */
    #[Computed]
    public function solicitudes(): Collection
    {
        return $this->consulta($this->pestana)
            ->when($this->prestacionId, fn (Builder $q) => $q->where('prestacion_id', $this->prestacionId))
            ->when($this->antiguedad > 0, fn (Builder $q) => $q->where('updated_at', '<=', now()->subDays($this->antiguedad)))
            ->with(['prestacion', 'ciudadano', 'responsable.profesional', 'sugerida.profesional', 'centroSolicitud'])
            ->oldest('updated_at')
            ->oldest('id')
            ->get()
            ->pipe(fn (Collection $solicitudes) => $this->ordenarEnMemoria($solicitudes));
    }

    /**
     * Columnas ordenables de la bandeja.
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function columnasOrdenables(): array
    {
        return ['persona' => 'asc', 'prestacion' => 'asc', 'centro' => 'asc', 'estado' => 'asc', 'responsable' => 'asc', 'movimiento' => 'asc'];
    }

    /**
     * Orden inicial: el último movimiento más antiguo primero (lo que más espera).
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function ordenInicial(): array
    {
        return ['movimiento', 'asc'];
    }

    /**
     * Ordena las solicitudes cargadas según la cabecera elegida.
     *
     * La bandeja no se pagina y el nombre de la persona está cifrado, así que se
     * ordena en memoria. El estado sigue el orden del circuito (el de los casos del
     * enum), no el alfabético. El orden de la consulta (último movimiento más antiguo)
     * queda como desempate, porque la ordenación de la colección es estable.
     *
     * @param Collection<int, SolicitudPrestacion> $solicitudes
     *
     * @return Collection<int, SolicitudPrestacion>
     */
    private function ordenarEnMemoria(Collection $solicitudes): Collection
    {
        $texto = fn (?string $valor): string => Str::lower(Str::ascii(trim((string) $valor)));
        $posicionEstado = array_flip(array_map(fn (EstadoSolicitudPrestacion $e) => $e->value, EstadoSolicitudPrestacion::cases()));

        $clave = match ($this->ordenarPor) {
            'persona' => fn (SolicitudPrestacion $s) => $texto($s->ciudadano?->nombre_completo),
            'prestacion' => fn (SolicitudPrestacion $s) => $texto($s->prestacion?->nombre),
            'centro' => fn (SolicitudPrestacion $s) => $texto($s->centroSolicitud?->nombre_corto ?? $s->centroSolicitud?->nombre),
            'estado' => fn (SolicitudPrestacion $s) => $posicionEstado[$s->estado->value] ?? PHP_INT_MAX,
            'responsable' => fn (SolicitudPrestacion $s) => $texto($s->responsable?->nombreVisible()),
            default => fn (SolicitudPrestacion $s) => $s->updated_at?->getTimestamp() ?? 0,
        };

        return $solicitudes->sortBy($clave, SORT_REGULAR, $this->direccionSql() === 'desc')->values();
    }

    /**
     * Número de solicitudes por pestaña.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function contadores(): array
    {
        return collect(['pendientes', 'preparacion', 'incidencias', 'enviadas'])
            ->mapWithKeys(fn (string $p) => [$p => $this->consulta($p)->count()])->all();
    }

    /**
     * Prestaciones con solicitudes en las unidades del usuario (para el filtro).
     *
     * @return Collection<int, Prestacion>
     */
    #[Computed]
    public function prestaciones(): Collection
    {
        return Prestacion::whereIn('id', $this->base()->select('prestacion_id'))->orderBy('nombre')->get(['id', 'nombre']);
    }

    /**
     * Cambia de pestaña.
     *
     * @param string $pestana
     *
     * @return void
     */
    public function verPestana(string $pestana): void
    {
        if (in_array($pestana, ['pendientes', 'preparacion', 'incidencias', 'enviadas'], true)) {
            $this->pestana = $pestana;
        }
    }

    /**
     * Pinta la bandeja.
     *
     * @return View
     */
    public function render(): View
    {
        return view('prestaciones::livewire.bandeja-tramitacion')->layout('layouts.tramitacion');
    }

    /**
     * Consulta de una pestaña.
     *
     * @param string $pestana
     *
     * @return Builder<SolicitudPrestacion>
     */
    private function consulta(string $pestana): Builder
    {
        $q = $this->base();

        return match ($pestana) {
            'preparacion' => $q->where('estado', EstadoSolicitudPrestacion::EnPreparacion->value)
                ->when($this->soloMias, fn (Builder $b) => $b->where('responsable_id', Auth::id())),
            'incidencias' => $q->where('estado', EstadoSolicitudPrestacion::Enviada->value)->conIncidenciaAbierta(),
            'enviadas' => $q->where('estado', EstadoSolicitudPrestacion::Enviada->value),
            default => $q->where('estado', EstadoSolicitudPrestacion::EnBandeja->value),
        };
    }

    /**
     * Solicitudes de las unidades del usuario.
     *
     * @return Builder<SolicitudPrestacion>
     */
    private function base(): Builder
    {
        return SolicitudPrestacion::query()->whereIn('unidad_tramitadora_id', Auth::user()->uoSubtreeIds());
    }
}

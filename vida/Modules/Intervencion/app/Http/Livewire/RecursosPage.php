<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Centro\Models\ListaEspera;
use Modules\Centro\Models\ListaEsperaMovimiento;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Usuarios\Models\Profesional;

/**
 * Pantalla de gestión de lista de espera y asignación de plazas.
 *
 * Solo visible para profesionales cuyo centro tiene plazas configuradas.
 * Pestaña «Pendientes»: prescripciones en estado pendiente o en_lista_espera.
 * Pestaña «Activas»: prescripciones en estado asignada o activa.
 *
 * @property-read LengthAwarePaginator $prescripcionesPendientes
 * @property-read LengthAwarePaginator $prescripcionesActivas
 * @property-read Collection $previsionLiberaciones
 */
#[Layout('layouts.operativo')]
class RecursosPage extends Component
{
    use WithPagination;

    /** @var string Pestaña activa */
    public string $pestana = 'pendientes';

    /** @var int|null ID de prescripción cuyo modal de asignación está abierto */
    public ?int $prescripcionAsignandoId = null;

    /** @var int|null ID de prescripción en proceso de cancelación */
    public ?int $prescripcionCancelandoId = null;

    /** @var string Motivo de cancelación */
    public string $motivoCancelacion = '';

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * Prescripciones dirigidas al ámbito del profesional en estados pendiente/en_lista_espera.
     *
     * @return LengthAwarePaginator<Prescripcion>
     */
    #[Computed]
    public function prescripcionesPendientes(): LengthAwarePaginator
    {
        $destinoIds = $this->destinoIdsDelCentro();

        return Prescripcion::whereIn('prescripciones.estado', ['pendiente', 'en_lista_espera'])
            ->where('prescripciones.tipo_destino', 'coleccion_plazas')
            ->whereIn('prescripciones.destino_id', $destinoIds)
            ->with(['ciudadano', 'listaEspera'])
            ->leftJoin('lista_espera', 'lista_espera.prescripcion_id', '=', 'prescripciones.id')
            ->orderByRaw('COALESCE(lista_espera.posicion, 9999) ASC')
            ->orderBy('prescripciones.fecha_prescripcion', 'asc')
            ->select('prescripciones.*')
            ->paginate(20);
    }

    /**
     * Prescripciones en estados asignada/activa del ámbito del profesional.
     *
     * @return LengthAwarePaginator<Prescripcion>
     */
    #[Computed]
    public function prescripcionesActivas(): LengthAwarePaginator
    {
        $destinoIds = $this->destinoIdsDelCentro();

        return Prescripcion::whereIn('estado', ['asignada', 'activa'])
            ->where('tipo_destino', 'coleccion_plazas')
            ->whereIn('destino_id', $destinoIds)
            ->with(['ciudadano', 'plaza'])
            ->orderBy('fecha_asignacion', 'desc')
            ->paginate(20);
    }

    /**
     * Prescripciones activas con fecha_fin en los próximos 30 días (previsión de liberaciones).
     *
     * @return Collection<int, Prescripcion>
     */
    #[Computed]
    public function previsionLiberaciones(): Collection
    {
        $destinoIds = $this->destinoIdsDelCentro();

        return Prescripcion::whereIn('estado', ['asignada', 'activa'])
            ->where('tipo_destino', 'coleccion_plazas')
            ->whereIn('destino_id', $destinoIds)
            ->whereNotNull('fecha_fin')
            ->whereBetween('fecha_fin', [today(), today()->addDays(30)])
            ->with(['ciudadano', 'plaza'])
            ->orderBy('fecha_fin')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de asignación para una prescripción.
     *
     * @param int $prescripcionId ID de la prescripción a asignar.
     */
    public function abrirModalAsignacion(int $prescripcionId): void
    {
        $this->prescripcionAsignandoId = $prescripcionId;
        $this->dispatch('abrir-asignar-plaza', prescripcionId: $prescripcionId);
    }

    /**
     * Mueve una prescripción a una nueva posición en la lista de espera.
     * Registra el movimiento en la tabla de auditoría.
     *
     * @param int $prescripcionId ID de la prescripción a mover.
     * @param int $nuevaPosicion  Nueva posición en la lista.
     */
    public function moverEnLista(int $prescripcionId, int $nuevaPosicion): void
    {
        $prescripcion = Prescripcion::findOrFail($prescripcionId);
        $listaEspera = $prescripcion->listaEspera;

        if (! $listaEspera) {
            return;
        }

        $posicionAnterior = $listaEspera->posicion;

        $listaEspera->update(['posicion' => $nuevaPosicion]);

        $profesionalId = $this->profesionalActual()?->id;
        if ($profesionalId) {
            ListaEsperaMovimiento::create([
                'lista_espera_id' => $listaEspera->id,
                'posicion_anterior' => $posicionAnterior,
                'posicion_nueva' => $nuevaPosicion,
                'profesional_id' => $profesionalId,
            ]);
        }

        unset($this->prescripcionesPendientes);
    }

    /**
     * Cancela una prescripción. Si tenía plaza asignada, la libera.
     *
     * @param int    $prescripcionId ID de la prescripción a cancelar.
     * @param string $motivo         Motivo obligatorio de cancelación.
     */
    public function cancelarPrescripcion(int $prescripcionId, string $motivo): void
    {
        $prescripcion = Prescripcion::findOrFail($prescripcionId);

        if ($prescripcion->plaza_id) {
            Plaza::where('id', $prescripcion->plaza_id)->update(['estado' => 'libre']);
        }

        if ($prescripcion->listaEspera) {
            $prescripcion->listaEspera->update(['estado' => 'cancelada']);
        }

        $prescripcion->update([
            'estado' => 'cancelada',
            'motivo_cancelacion' => $motivo,
        ]);

        unset($this->prescripcionesPendientes, $this->prescripcionesActivas);
    }

    /**
     * Marca una prescripción como activa (inicio efectivo del servicio).
     *
     * @param int    $prescripcionId ID de la prescripción.
     * @param string $fechaInicio    Fecha de inicio efectivo (Y-m-d).
     */
    public function marcarActiva(int $prescripcionId, string $fechaInicio): void
    {
        Prescripcion::where('id', $prescripcionId)->update([
            'estado' => 'activa',
            'fecha_inicio' => $fechaInicio,
        ]);

        unset($this->prescripcionesActivas);
    }

    /**
     * Marca una prescripción como finalizada.
     *
     * @param int $prescripcionId ID de la prescripción.
     */
    public function marcarFinalizada(int $prescripcionId): void
    {
        $prescripcion = Prescripcion::findOrFail($prescripcionId);

        if ($prescripcion->plaza_id) {
            Plaza::where('id', $prescripcion->plaza_id)->update(['estado' => 'libre']);
        }

        $prescripcion->update([
            'estado' => 'finalizada',
            'fecha_fin' => today()->toDateString(),
        ]);

        unset($this->prescripcionesActivas);
    }

    /**
     * Cambia la pestaña activa.
     *
     * @param string $pestana 'pendientes' | 'activas'
     */
    public function cambiarPestana(string $pestana): void
    {
        $this->pestana = $pestana;
        $this->resetPage();
    }

    /**
     * Refresca la lista tras una asignación realizada en AsignarPlazaModal.
     */
    #[On('plaza-asignada')]
    public function refrescarTrasAsignacion(): void
    {
        $this->prescripcionAsignandoId = null;
        unset($this->prescripcionesPendientes, $this->prescripcionesActivas);
    }

    // -------------------------------------------------------------------------
    // Utilidades privadas
    // -------------------------------------------------------------------------

    /**
     * IDs de ColeccionPlazas pertenecientes al centro del profesional autenticado.
     *
     * @return list<int>
     */
    private function destinoIdsDelCentro(): array
    {
        $profesional = $this->profesionalActual();
        if (! $profesional) {
            return [];
        }

        $centroId = $profesional->unidad_organizativa_id
            ? \Modules\Centro\Models\Centro::where('unidad_organizativa_id', $profesional->unidad_organizativa_id)
                ->value('id')
            : null;

        if (! $centroId) {
            return [];
        }

        return \Modules\Centro\Models\ColeccionPlazas::where('centro_id', $centroId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Profesional asociado al usuario autenticado (via users.profesional_id).
     */
    private function profesionalActual(): ?Profesional
    {
        $profesionalId = Auth::user()?->profesional_id;

        return $profesionalId ? Profesional::find($profesionalId) : null;
    }

    /**
     * Renderiza la página de recursos.
     */
    public function render(): View
    {
        return view('intervencion::livewire.recursos-page');
    }
}

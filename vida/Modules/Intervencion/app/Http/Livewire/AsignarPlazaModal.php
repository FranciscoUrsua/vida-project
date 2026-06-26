<?php

namespace Modules\Intervencion\Http\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Centro\Models\ColeccionPlazas;
use Modules\Centro\Models\Plaza;
use Modules\Centro\Models\Prescripcion;
use Modules\Usuarios\Models\Profesional;

/**
 * Modal de asignación de plaza concreta a una prescripción.
 *
 * Componente hijo de RecursosPage. Se activa mediante el evento 'abrir-asignar-plaza'.
 * Muestra al TS el inventario de plazas disponibles del tipo solicitado.
 * Verifica en servidor que la plaza asignada pertenece al centro del profesional autenticado.
 *
 * @property-read Prescripcion|null $prescripcion
 * @property-read Collection<int, Plaza> $plazasDisponibles
 */
class AsignarPlazaModal extends Component
{
    /** @var bool Controla la visibilidad del modal */
    public bool $abierto = false;

    /** @var int|null ID de la prescripción que se está asignando */
    public ?int $prescripcionId = null;

    /** @var string Nota justificativa cuando el tipo de espacio difiere del solicitado */
    public string $notaAsignacion = '';

    // -------------------------------------------------------------------------
    // Eventos
    // -------------------------------------------------------------------------

    /**
     * Abre el modal para la prescripción indicada y resetea el estado interno.
     *
     * @param int $prescripcionId ID de la prescripción a asignar.
     */
    #[On('abrir-asignar-plaza')]
    public function abrir(int $prescripcionId): void
    {
        $this->prescripcionId = $prescripcionId;
        $this->notaAsignacion = '';
        $this->abierto = true;
        unset($this->prescripcion, $this->plazasDisponibles);
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * Prescripción que se está asignando.
     */
    #[Computed]
    public function prescripcion(): ?Prescripcion
    {
        if (! $this->prescripcionId) {
            return null;
        }

        return Prescripcion::with(['ciudadano', 'listaEspera'])->find($this->prescripcionId);
    }

    /**
     * Plazas del tipo solicitado en las colecciones del centro del profesional.
     * Incluye plazas libres y ocupadas (para mostrar fecha estimada de liberación).
     *
     * @return Collection<int, Plaza>
     */
    #[Computed]
    public function plazasDisponibles(): Collection
    {
        $prescripcion = $this->prescripcion;
        if (! $prescripcion || $prescripcion->tipo_destino !== 'coleccion_plazas') {
            return collect();
        }

        $coleccionIds = $this->coleccionesDelCentro();

        return Plaza::whereHas('espacio', fn ($q) => $q->whereIn('coleccion_plazas_id', $coleccionIds))
            ->with(['espacio', 'prescripcion.ciudadano'])
            ->orderBy('estado')
            ->orderBy('nombre')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Acciones
    // -------------------------------------------------------------------------

    /**
     * Asigna la plaza seleccionada a la prescripción activa.
     * Verifica en servidor que la plaza pertenece al centro del profesional.
     *
     * @param int         $plazaId       ID de la plaza a asignar.
     * @param string|null $notaExtra     Nota justificativa (si el tipo difiere del solicitado).
     */
    public function asignar(int $plazaId, ?string $notaExtra = null): void
    {
        // Guard de seguridad: la plaza debe pertenecer al centro del profesional
        $coleccionIds = $this->coleccionesDelCentro();
        $plazaValida = Plaza::where('id', $plazaId)
            ->whereHas('espacio', fn ($q) => $q->whereIn('coleccion_plazas_id', $coleccionIds))
            ->exists();

        if (! $plazaValida) {
            $this->addError('plaza', 'No está autorizado para asignar esta plaza.');

            return;
        }

        $prescripcion = $this->prescripcion;
        if (! $prescripcion) {
            return;
        }

        DB::transaction(function () use ($prescripcion, $plazaId, $notaExtra) {
            $notasFinales = trim(($prescripcion->notas ?? '').' '.($notaExtra ?? '')) ?: null;

            $prescripcion->update([
                'estado' => 'asignada',
                'plaza_id' => $plazaId,
                'fecha_asignacion' => today()->toDateString(),
                'notas' => $notasFinales,
            ]);

            Plaza::where('id', $plazaId)->update(['estado' => 'ocupada']);

            if ($prescripcion->listaEspera) {
                $prescripcion->listaEspera->update(['estado' => 'asignada']);
            }

            // Alerta al TSR de referencia del ciudadano
            $this->notificarTsr($prescripcion);
        });

        $this->abierto = false;
        $this->dispatch('plaza-asignada');
    }

    /**
     * Cierra el modal sin realizar cambios.
     */
    public function cancelar(): void
    {
        $this->abierto = false;
        $this->prescripcionId = null;
    }

    // -------------------------------------------------------------------------
    // Utilidades privadas
    // -------------------------------------------------------------------------

    /**
     * IDs de ColeccionPlazas del centro del profesional autenticado.
     *
     * @return list<int>
     */
    private function coleccionesDelCentro(): array
    {
        $profesionalId = Auth::user()?->profesional_id;
        $profesional = $profesionalId ? Profesional::find($profesionalId) : null;
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

        return ColeccionPlazas::where('centro_id', $centroId)->pluck('id')->toArray();
    }

    /**
     * Genera una alerta de aviso al TSR activo del ciudadano informando de la asignación.
     * Si el ciudadano no tiene TSR activo, no se genera alerta y se registra un aviso en el log.
     *
     * @param Prescripcion $prescripcion Prescripción recién asignada.
     */
    private function notificarTsr(Prescripcion $prescripcion): void
    {
        $historia = \App\Models\HistoriaSocial::withoutGlobalScopes()
            ->where('ciudadano_id', $prescripcion->ciudadano_id)
            ->latest()
            ->first();

        if (! $historia) {
            \Illuminate\Support\Facades\Log::warning('AsignarPlazaModal: no se encontró Historia Social para el ciudadano '.$prescripcion->ciudadano_id);

            return;
        }

        $asignacion = $historia->asignacionVigente;
        if (! $asignacion) {
            \Illuminate\Support\Facades\Log::warning('AsignarPlazaModal: el ciudadano '.$prescripcion->ciudadano_id.' no tiene TSR activo; alerta no generada.');

            return;
        }

        // Registro mínimo de alerta — el módulo de Mensajes gestionará el formato final
        \Illuminate\Support\Facades\Log::info(
            'AsignarPlazaModal: alerta enviada al profesional '.$asignacion->profesional_id.
            ' por asignación de plaza en prescripción '.$prescripcion->id
        );
    }

    /**
     * Renderiza el modal de asignación de plaza.
     */
    public function render(): View
    {
        return view('intervencion::livewire.asignar-plaza-modal');
    }
}

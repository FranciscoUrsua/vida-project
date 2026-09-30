<?php

namespace Modules\Agenda\Livewire\Citas;

use App\Models\Ciudadano;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;
use Modules\Agenda\Enums\CanalSolicitudCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Livewire\Citas\Concerns\BuscaPersonas;
use Modules\Agenda\Livewire\Citas\Concerns\ConLayoutDeCitas;
use Modules\Agenda\Livewire\Citas\Concerns\FormularioSolicitudCita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\BusquedaHuecosService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\PropuestaHueco;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Atencion\Models\RegistroAtencion;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;

/**
 * Cita en ventanilla o por teléfono (docs/modulo-citas.md §3.2).
 *
 * Se busca a la persona, se eligen tipo, urgencia y destino, el sistema propone
 * huecos y quien atiende elige uno. Al confirmar, CitacionService::citarDirecto()
 * crea a la vez la solicitud y la cita, o ninguna. Si se llega desde un
 * registro de atención (`?atencion=`), la cita queda como su cita generada.
 *
 * @property-read Ciudadano|null $persona
 * @property-read Centro|null $centro
 */
class CitaDirectaPage extends Component
{
    use BuscaPersonas;
    use ConLayoutDeCitas;
    use FormularioSolicitudCita;

    /** @var int|null Persona a la que se da la cita. */
    #[Url(as: 'ciudadano', except: null)]
    public ?int $ciudadanoId = null;

    /** @var int|null Registro de atención del que nace la cita, si lo hay. */
    #[Url(as: 'atencion', except: null)]
    public ?int $atencionId = null;

    /** @var string Canal: 'presencial' o 'telefonico'. */
    public string $canal = 'presencial';

    /** @var list<array{slot_id: int, fecha: string, hora: string, profesional: string, modo: string, modo_valor: string}>|null */
    public ?array $propuestas = null;

    /**
     * Solo quien da citas en su centro.
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    public function mount(): void
    {
        abort_if($this->centro === null, 403, 'Tu unidad no tiene centro asociado.');
        Gate::authorize('verBandeja', [SolicitudCita::class, $this->centro]);

        // Sin referencia no se puede pedir para la referencia: por defecto, el primer libre
        $this->formSolicitud['destino'] = 'primer_libre';
    }

    /**
     * Persona elegida.
     *
     * @return Ciudadano|null
     */
    #[Computed]
    public function persona(): ?Ciudadano
    {
        return $this->ciudadanoId ? Ciudadano::withoutGlobalScopes()->with('documentoVigente')->find($this->ciudadanoId) : null;
    }

    /**
     * Centro de quien da la cita.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centro(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroActivo(Auth::user());
    }

    /**
     * Elige a la persona encontrada.
     *
     * @param int $ciudadanoId
     * @return void
     */
    public function elegirPersona(int $ciudadanoId): void
    {
        $this->ciudadanoId = $ciudadanoId;
        $this->propuestas = null;
        $this->limpiarBusquedaPersona();
        unset($this->persona);
    }

    /**
     * Vuelve a buscar a otra persona.
     *
     * @return void
     */
    public function cambiarPersona(): void
    {
        $this->ciudadanoId = null;
        $this->propuestas = null;
        unset($this->persona);
    }

    /**
     * Propone huecos con los datos del formulario (sin guardar nada).
     *
     * @return void
     */
    public function buscarHuecos(): void
    {
        if ($this->persona === null) {
            return;
        }

        $borrador = app(SolicitudCitaService::class)->borrador($this->datos(), Auth::user());

        $this->propuestas = app(BusquedaHuecosService::class)->buscar($borrador)
            ->map(fn (PropuestaHueco $p) => [
                'slot_id' => $p->slot->id,
                'fecha' => $p->slot->fecha->locale('es')->isoFormat('ddd D MMM YYYY'),
                'hora' => substr((string) $p->slot->hora_inicio, 0, 5),
                'profesional' => $p->profesional->nombre_completo,
                'modo' => $p->modo->label(),
                'modo_valor' => $p->modo->value,
            ])
            ->all();
    }

    /**
     * Da la cita en el hueco elegido: solicitud y cita a la vez, o ninguna.
     *
     * @param int $slotId
     * @return void
     */
    public function citar(int $slotId): void
    {
        $propuesta = collect($this->propuestas ?? [])->firstWhere('slot_id', $slotId);

        if ($propuesta === null || $this->persona === null) {
            return;
        }

        try {
            $cita = app(CitacionService::class)->citarDirecto(
                $this->datos(),
                Slot::findOrFail($slotId),
                ModoAsignacionCita::from($propuesta['modo_valor']),
                Auth::user(),
            );
        } catch (LogicException|InvalidArgumentException $e) {
            $this->addError('citar', $e->getMessage().' Vuelve a buscar huecos.');
            $this->propuestas = null;

            return;
        }

        $this->anotarEnAtencion($cita->id);

        session()->flash('aviso-cita', 'Cita dada.');
        $this->redirectRoute('agenda.citas.show', $cita, navigate: true);
    }

    /**
     * Pinta la página.
     *
     * @return View
     */
    public function render(): View
    {
        return $this->vistaConLayout('agenda::livewire.citas.cita-directa-page');
    }

    /**
     * @return Centro|null
     */
    protected function centroDeLaSolicitud(): ?Centro
    {
        return $this->centro;
    }

    /**
     * Datos de la solicitud para el servicio.
     *
     * @return array<string, mixed>
     */
    private function datos(): array
    {
        $canal = $this->canal === CanalSolicitudCita::Telefonico->value ? CanalSolicitudCita::Telefonico : CanalSolicitudCita::Presencial;

        return $this->datosSolicitud((int) $this->ciudadanoId, $this->centro->id, $canal->value);
    }

    /**
     * Si la cita nace de un registro de atención de la misma persona, queda
     * como su cita generada (distinta de la cita que el registro atiende).
     *
     * @param int $citaId
     * @return void
     */
    private function anotarEnAtencion(int $citaId): void
    {
        if ($this->atencionId === null) {
            return;
        }

        RegistroAtencion::whereKey($this->atencionId)
            ->where('ciudadano_id', $this->ciudadanoId)
            ->whereNull('cita_generada_id')
            ->first()
            ?->update(['cita_generada_id' => $citaId]);
    }
}

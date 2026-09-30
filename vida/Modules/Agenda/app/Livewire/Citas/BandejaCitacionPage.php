<?php

namespace Modules\Agenda\Livewire\Citas;

use App\Models\Ciudadano;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use LogicException;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\ModoAsignacionCita;
use Modules\Agenda\Livewire\Citas\Concerns\BuscaPersonas;
use Modules\Agenda\Livewire\Citas\Concerns\ConLayoutDeCitas;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Models\SolicitudCita;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\BusquedaHuecosService;
use Modules\Agenda\Services\Citas\CitacionService;
use Modules\Agenda\Services\Citas\PropuestaHueco;
use Modules\Agenda\Services\Citas\SolicitudCitaService;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Usuarios\Models\Cargo;

/**
 * Bandeja de citación del centro (docs/modulo-citas.md §6), para
 * `consulta_basica` y supervisión.
 *
 * Solicitudes pendientes y en gestión por urgencia y fecha límite, citas
 * externas pendientes de identificar y las citas del centro de un día (desde
 * donde se abre cada cita para reprogramarla o cancelarla). Del tipo de cita
 * solo se muestra la etiqueta pública y nunca el motivo profesional: quien da
 * la cita no debe poder deducir de qué trata (§2.1). Todas las acciones pasan
 * por los servicios de Agenda, que comprueban la política y dejan evento.
 *
 * @property-read Centro $centro
 * @property-read Collection<int, SolicitudCita> $solicitudes
 * @property-read Collection<int, Cita> $pendientesIdentificar
 * @property-read Collection<int, Cita> $citasDelDia
 * @property-read array<string, string> $perfiles
 */
class BandejaCitacionPage extends Component
{
    use BuscaPersonas;
    use ConLayoutDeCitas;

    /** @var string Pestaña: 'solicitudes', 'identificar' o 'citas'. */
    #[Url(as: 'ver', except: 'solicitudes')]
    public string $pestana = 'solicitudes';

    /** @var string Día de la lista de citas del centro (Y-m-d). */
    public string $dia = '';

    /** @var int|null Solicitud para la que se muestran huecos. */
    public ?int $solicitudCitando = null;

    /** @var list<array{slot_id: int, fecha: string, hora: string, profesional: string, modo: string, modo_valor: string}>|null */
    public ?array $propuestas = null;

    /** @var array{id: int|null, accion: string, texto: string} Soltar, desistir o anular: nota o motivo. */
    public array $cierre = ['id' => null, 'accion' => '', 'texto' => ''];

    /** @var int|null Cita externa que se está identificando. */
    public ?int $citaIdentificando = null;

    /** @var string|null Resultado de la última acción. */
    public ?string $aviso = null;

    /**
     * Comprueba que el usuario ve la bandeja de su centro.
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    public function mount(): void
    {
        abort_if($this->centroOrNull() === null, 403, 'Tu unidad no tiene centro asociado.');
        Gate::authorize('verBandeja', [SolicitudCita::class, $this->centro]);
        $this->dia = today()->toDateString();
    }

    /**
     * Centro de la UO activa del usuario.
     *
     * @return Centro
     */
    #[Computed]
    public function centro(): Centro
    {
        return $this->centroOrNull();
    }

    /**
     * Solicitudes abiertas del centro en el orden de la bandeja.
     *
     * @return Collection<int, SolicitudCita>
     */
    #[Computed]
    public function solicitudes(): Collection
    {
        return SolicitudCita::with(['ciudadano.documentoVigente', 'tipoCita', 'profesionalDestino.profesional', 'gestionadaPor.profesional'])
            ->where('centro_id', $this->centro->id)
            ->abiertas()
            ->ordenBandeja()
            ->get();
    }

    /**
     * Nombre de cada perfil (cargo) por su slug, para el destino «servicio».
     *
     * @return array<string, string>
     */
    #[Computed]
    public function perfiles(): array
    {
        return Cargo::pluck('nombre', 'slug')->all();
    }

    /**
     * Citas externas del centro que esperan a que se identifique a la persona.
     *
     * @return Collection<int, Cita>
     */
    #[Computed]
    public function pendientesIdentificar(): Collection
    {
        return Cita::with('profesional.profesional')
            ->where('centro_id', $this->centro->id)
            ->whereNull('ciudadano_id')
            ->where('estado', EstadoCita::Confirmada)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Citas del centro en el día elegido.
     *
     * @return Collection<int, Cita>
     */
    #[Computed]
    public function citasDelDia(): Collection
    {
        return Cita::with(['ciudadano', 'profesional.profesional', 'tipoCita'])
            ->where('centro_id', $this->centro->id)
            ->whereDate('fecha', $this->dia ?: today())
            ->whereNotIn('estado', [EstadoCita::Reprogramada])
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Cambia de pestaña.
     *
     * @param string $pestana
     * @return void
     */
    public function verPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, ['solicitudes', 'identificar', 'citas'], true) ? $pestana : 'solicitudes';
        $this->reiniciarPaneles();
    }

    /**
     * Toma una solicitud para gestionarla (nadie más la llamará mientras tanto).
     *
     * @param int $id
     * @return void
     */
    public function tomar(int $id): void
    {
        $this->ejecutar(fn () => app(SolicitudCitaService::class)->tomar($this->solicitud($id), Auth::user()), 'Solicitud tomada.');
    }

    /**
     * Abre el formulario de soltar, desistir o anular.
     *
     * @param int $id
     * @param string $accion 'soltar', 'desistir' o 'anular'.
     * @return void
     */
    public function prepararCierre(int $id, string $accion): void
    {
        $this->reiniciarPaneles();
        $this->cierre = ['id' => $id, 'accion' => in_array($accion, ['soltar', 'desistir', 'anular'], true) ? $accion : 'soltar', 'texto' => ''];
    }

    /**
     * Suelta (con nota opcional), desiste o anula (con motivo obligatorio).
     *
     * @return void
     */
    public function confirmarCierre(): void
    {
        $solicitud = $this->solicitud((int) $this->cierre['id']);
        $texto = $this->cierre['texto'];
        $servicio = app(SolicitudCitaService::class);

        $hecho = $this->ejecutar(fn () => match ($this->cierre['accion']) {
            'desistir' => $servicio->desistir($solicitud, Auth::user(), $texto),
            'anular' => $servicio->anular($solicitud, Auth::user(), $texto),
            default => $servicio->liberar($solicitud, Auth::user(), $texto ?: null),
        }, match ($this->cierre['accion']) {
            'desistir' => 'Solicitud cerrada como desistida.',
            'anular' => 'Solicitud anulada.',
            default => 'Solicitud devuelta a pendiente.',
        });

        if ($hecho) {
            $this->cierre = ['id' => null, 'accion' => '', 'texto' => ''];
        }
    }

    /**
     * Propone huecos para una solicitud.
     *
     * @param int $id
     * @return void
     *
     * @throws AuthorizationException
     */
    public function buscarHuecos(int $id): void
    {
        $solicitud = $this->solicitud($id);
        $this->authorize('gestionar', $solicitud);
        $this->reiniciarPaneles();
        $this->solicitudCitando = $id;

        $this->propuestas = app(BusquedaHuecosService::class)->buscar($solicitud)
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
     * Da la cita en el hueco elegido.
     *
     * @param int $slotId
     * @return void
     */
    public function citar(int $slotId): void
    {
        $propuesta = collect($this->propuestas ?? [])->firstWhere('slot_id', $slotId);

        if ($this->solicitudCitando === null || $propuesta === null) {
            return;
        }

        $solicitud = $this->solicitud($this->solicitudCitando);

        $this->ejecutar(fn () => app(CitacionService::class)->citar(
            $solicitud,
            Slot::findOrFail($slotId),
            ModoAsignacionCita::from($propuesta['modo_valor']),
            Auth::user(),
        ), "Cita dada: {$propuesta['fecha']} a las {$propuesta['hora']} con {$propuesta['profesional']}.");
    }

    /**
     * Abre la identificación de una cita externa.
     *
     * @param int $citaId
     * @return void
     */
    public function prepararIdentificacion(int $citaId): void
    {
        $this->reiniciarPaneles();
        $this->citaIdentificando = $citaId;
    }

    /**
     * Asocia la cita externa a la persona elegida.
     *
     * @param int $ciudadanoId
     * @return void
     */
    public function identificar(int $ciudadanoId): void
    {
        $cita = Cita::where('centro_id', $this->centro->id)->findOrFail($this->citaIdentificando);
        $ciudadano = Ciudadano::withoutGlobalScopes()->findOrFail($ciudadanoId);

        $this->ejecutar(fn () => app(AtencionCitaService::class)->identificarCiudadano($cita, $ciudadano, Auth::user()), 'Persona identificada.');
    }

    /**
     * Cierra los paneles abiertos.
     *
     * @return void
     */
    public function reiniciarPaneles(): void
    {
        $this->solicitudCitando = null;
        $this->propuestas = null;
        $this->citaIdentificando = null;
        $this->cierre = ['id' => null, 'accion' => '', 'texto' => ''];
        $this->limpiarBusquedaPersona();
        $this->resetErrorBag();
    }

    /**
     * Pinta la bandeja.
     *
     * @return View
     */
    public function render(): View
    {
        return $this->vistaConLayout('agenda::livewire.citas.bandeja-citacion-page');
    }

    /**
     * Solicitud del centro (una de otro centro no existe para esta pantalla).
     *
     * @param int $id
     * @return SolicitudCita
     */
    private function solicitud(int $id): SolicitudCita
    {
        return SolicitudCita::where('centro_id', $this->centro->id)->findOrFail($id);
    }

    /**
     * Ejecuta una acción de servicio: si falla por una regla del dominio, lo
     * muestra; si va bien, deja el aviso y recarga las listas.
     *
     * @param callable(): mixed $accion
     * @param string $ok
     * @return bool
     */
    private function ejecutar(callable $accion, string $ok): bool
    {
        try {
            $accion();
        } catch (LogicException|InvalidArgumentException $e) {
            $this->addError('accion', $e->getMessage());

            return false;
        }

        $this->reiniciarPaneles();
        $this->aviso = $ok;
        unset($this->solicitudes, $this->pendientesIdentificar, $this->citasDelDia);

        return true;
    }

    /**
     * @return Centro|null
     */
    private function centroOrNull(): ?Centro
    {
        return app(CentroDeUsuario::class)->centroActivo(Auth::user());
    }
}

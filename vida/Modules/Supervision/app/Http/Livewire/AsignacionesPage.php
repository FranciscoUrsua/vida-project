<?php

namespace Modules\Supervision\Http\Livewire;

use App\Models\CatalogoSistema;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use LogicException;
use Modules\Centro\Enums\TipoAsignacionPendiente;
use Modules\Centro\Models\AsignacionPendiente;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\AsignacionCentroService;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\RepartoCasos;
use Modules\Intervencion\Services\Asignacion\ActividadCasosService;
use Modules\Intervencion\Services\Asignacion\AsignacionReferenciaService;
use Modules\Intervencion\Services\Asignacion\BandejaAsignacionesService;
use Modules\Intervencion\Services\Asignacion\PoolReferenciaService;
use Modules\Intervencion\Services\Asignacion\RepartoCasosService;

/**
 * Bandeja de asignaciones del supervisor del centro (docs/modulo-asignacion.md §7).
 *
 * Pestaña «Pendientes»: personas sin centro, historias sin referencia,
 * propuestas de cambio de centro por domicilio y repartos por salida sin
 * confirmar. Cada caso se resuelve a mano y con motivo (RN-04, RN-09, RN-10).
 * Pestaña «Actividad del equipo»: casos asignados, con actividad y dormidos por
 * profesional, desde donde se inicia el reparto de los casos de quien sale.
 *
 * Solo ve y resuelve lo de su centro: toda entrada se vuelve a cargar con el
 * alcance del centro antes de actuar, nunca se confía en el id del navegador.
 *
 * @property-read Centro|null $centro
 * @property-read EloquentCollection<int, AsignacionPendiente> $sinCentro
 * @property-read EloquentCollection<int, AsignacionPendiente> $sinReferencia
 * @property-read EloquentCollection<int, AsignacionPendiente> $cambiosDomicilio
 * @property-read EloquentCollection<int, RepartoCasos> $repartos
 * @property-read Collection<int, array{profesional: User, asignados: int, con_actividad: int, dormidos: int}> $actividad
 * @property-read Collection<int, User> $profesionalesReferencia
 * @property-read array<string, string> $tiposCentro
 * @property string $pestana
 * @property int|null $resolviendoId
 * @property string $accion
 * @property int|null $centroElegidoId
 * @property int|null $profesionalElegidoId
 * @property string $motivo
 * @property int|null $repartoOrigenId
 * @property string $motivoReparto
 * @property string|null $aviso
 */
#[Layout('layouts.supervision')]
class AsignacionesPage extends Component
{
    /** @var string Pestaña activa: pendientes o actividad. */
    public string $pestana = 'pendientes';

    /** @var int|null Entrada de la bandeja que se está resolviendo. */
    public ?int $resolviendoId = null;

    /** @var string Acción sobre la entrada: asignar, confirmar o descartar. */
    public string $accion = '';

    /** @var int|null Centro elegido en una asignación manual de centro. */
    public ?int $centroElegidoId = null;

    /** @var int|null Profesional elegido como referencia. */
    public ?int $profesionalElegidoId = null;

    /** @var string Motivo de la decisión, obligatorio. */
    public string $motivo = '';

    /** @var int|null Profesional cuyos casos se van a repartir. */
    public ?int $repartoOrigenId = null;

    /** @var string Motivo del reparto, obligatorio. */
    public string $motivoReparto = '';

    /** @var string|null Confirmación de la última decisión. */
    public ?string $aviso = null;

    /**
     * Fija la pestaña de la ruta.
     *
     * @param string|null $pestana
     * @return void
     */
    public function mount(?string $pestana = null): void
    {
        $this->pestana = $pestana ?? 'pendientes';
    }

    /**
     * Centro que supervisa el usuario, o null si no supervisa ninguno.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centro(): ?Centro
    {
        $usuario = auth()->user();
        $servicio = app(CentroDeUsuario::class);
        $centro = $servicio->centroActivo($usuario);

        return $centro !== null && $servicio->supervisa($usuario, $centro) ? $centro : null;
    }

    /**
     * Personas sin centro del alcance del centro.
     *
     * @return EloquentCollection<int, AsignacionPendiente>
     */
    #[Computed]
    public function sinCentro(): EloquentCollection
    {
        return $this->pendientesDe(TipoAsignacionPendiente::SinCentro);
    }

    /**
     * Historias sin profesional de referencia.
     *
     * @return EloquentCollection<int, AsignacionPendiente>
     */
    #[Computed]
    public function sinReferencia(): EloquentCollection
    {
        return $this->pendientesDe(TipoAsignacionPendiente::SinReferencia);
    }

    /**
     * Propuestas de cambio de centro por cambio de domicilio.
     *
     * @return EloquentCollection<int, AsignacionPendiente>
     */
    #[Computed]
    public function cambiosDomicilio(): EloquentCollection
    {
        return $this->pendientesDe(TipoAsignacionPendiente::CambioDomicilio);
    }

    /**
     * Repartos por salida pendientes de confirmar.
     *
     * @return EloquentCollection<int, RepartoCasos>
     */
    #[Computed]
    public function repartos(): EloquentCollection
    {
        return $this->centro ? app(BandejaAsignacionesService::class)->repartosPropuestos($this->centro) : new EloquentCollection();
    }

    /**
     * Resumen de actividad de los casos por profesional.
     *
     * @return Collection<int, array{profesional: User, asignados: int, con_actividad: int, dormidos: int}>
     */
    #[Computed]
    public function actividad(): Collection
    {
        return $this->centro ? app(ActividadCasosService::class)->resumen($this->centro) : collect();
    }

    /**
     * Profesionales del centro que pueden ser referencia (para la asignación manual).
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function profesionalesReferencia(): Collection
    {
        return $this->centro ? app(PoolReferenciaService::class)->profesionalesReferencia($this->centro, today()) : collect();
    }

    /**
     * Etiquetas de los tipos de centro del catálogo.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tiposCentro(): array
    {
        return CatalogoSistema::opcionesParaSelect('centro.tipo');
    }

    /**
     * Centros activos de un tipo, para asignar a mano una persona sin centro.
     *
     * @param string|null $tipoCentro
     * @return EloquentCollection<int, Centro>
     */
    public function centrosDelTipo(?string $tipoCentro): EloquentCollection
    {
        return Centro::where('tipo_centro', $tipoCentro)->where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Abre el formulario de una entrada de la bandeja.
     *
     * @param int $id
     * @param string $accion asignar, confirmar o descartar.
     * @return void
     */
    public function iniciar(int $id, string $accion): void
    {
        $this->reset(['centroElegidoId', 'profesionalElegidoId', 'motivo', 'aviso']);
        $this->resetErrorBag();
        $this->resolviendoId = $id;
        $this->accion = $accion;

        $pendiente = $this->cargarPendiente($id);

        // Si hay un único centro candidato, se ofrece ya elegido
        if ($pendiente?->centros_candidatos !== null && count($pendiente->centros_candidatos) === 1) {
            $this->centroElegidoId = $pendiente->centros_candidatos[0];
        }
    }

    /**
     * Cierra el formulario abierto sin hacer nada.
     *
     * @return void
     */
    public function cancelar(): void
    {
        $this->reset(['resolviendoId', 'accion', 'centroElegidoId', 'profesionalElegidoId', 'motivo', 'repartoOrigenId', 'motivoReparto']);
        $this->resetErrorBag();
    }

    /**
     * Aplica la decisión del supervisor sobre la entrada abierta.
     *
     * @return void
     */
    public function resolver(): void
    {
        $this->validate(['motivo' => 'required|string|max:1000'], ['motivo.required' => 'Indica el motivo de la decisión.']);

        $pendiente = $this->resolviendoId ? $this->cargarPendiente($this->resolviendoId) : null;

        if ($pendiente === null) {
            $this->addError('motivo', 'Esta entrada ya no está pendiente en tu bandeja.');

            return;
        }

        try {
            $this->aplicar($pendiente, auth()->user());
        } catch (AuthorizationException|InvalidArgumentException|LogicException $e) {
            $this->addError('motivo', $e->getMessage());

            return;
        }

        // Sin el nombre de un protegido que la policy no deja ver (CLAUDE.md §3)
        $ciudadano = $pendiente->ciudadano;
        $nombre = $ciudadano !== null && (! $ciudadano->colectivo_extra_protegido || Gate::allows('view', $ciudadano))
            ? $ciudadano->nombre_completo
            : 'la persona con protección especial';
        $this->cancelar();
        $this->aviso = "Decisión registrada para {$nombre}.";
        $this->limpiarCache();
        $this->dispatch('asignaciones-actualizadas');
    }

    /**
     * Abre el formulario para repartir los casos de un profesional.
     *
     * @param int $usuarioId
     * @return void
     */
    public function iniciarReparto(int $usuarioId): void
    {
        $this->cancelar();
        $this->aviso = null;
        $this->repartoOrigenId = $usuarioId;
    }

    /**
     * Propone el reparto y lleva a la pantalla de revisión. No cambia ninguna asignación.
     *
     * @return void
     */
    public function proponerReparto(): void
    {
        $this->validate(['motivoReparto' => 'required|string|max:1000'], ['motivoReparto.required' => 'Indica el motivo del reparto.']);

        $origen = $this->actividad->first(fn (array $fila) => $fila['profesional']->id === $this->repartoOrigenId)['profesional'] ?? null;

        if ($this->centro === null || $origen === null) {
            $this->addError('motivoReparto', 'Ese profesional no tiene casos en el centro.');

            return;
        }

        try {
            $reparto = app(RepartoCasosService::class)->proponer($this->centro, $origen, $this->motivoReparto, auth()->user());
        } catch (AuthorizationException|InvalidArgumentException|LogicException $e) {
            $this->addError('motivoReparto', $e->getMessage());

            return;
        }

        $this->redirectRoute('supervision.asignaciones.reparto', $reparto, navigate: true);
    }

    /**
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.asignaciones-page');
    }

    /**
     * Ejecuta la acción abierta con el servicio que corresponde.
     *
     * @param AsignacionPendiente $pendiente
     * @param User $supervisor
     * @return void
     *
     * @throws AuthorizationException|InvalidArgumentException|LogicException
     */
    private function aplicar(AsignacionPendiente $pendiente, User $supervisor): void
    {
        $centros = app(AsignacionCentroService::class);

        // Una historia no puede quedarse sin referencia: solo se descartan las demás
        if ($this->accion === 'descartar' && $pendiente->tipo !== TipoAsignacionPendiente::SinReferencia) {
            $centros->descartar($pendiente, $this->motivo, $supervisor);

            return;
        }

        if ($this->accion === 'confirmar' && $pendiente->tipo === TipoAsignacionPendiente::CambioDomicilio) {
            $centros->confirmarCambioDomicilio($pendiente, $this->motivo, $supervisor);

            return;
        }

        if ($this->accion === 'asignar' && $pendiente->tipo === TipoAsignacionPendiente::SinCentro) {
            $centro = $this->centrosDelTipo($pendiente->tipo_centro)->firstWhere('id', $this->centroElegidoId)
                ?? throw new LogicException('Elige un centro activo del tipo que corresponde.');
            $centros->asignarManual($pendiente->ciudadano, $centro, $this->motivo, $supervisor);

            return;
        }

        if ($this->accion === 'asignar' && $pendiente->tipo === TipoAsignacionPendiente::SinReferencia && $pendiente->historia !== null) {
            $profesional = $this->profesionalesReferencia->firstWhere('id', $this->profesionalElegidoId)
                ?? throw new LogicException('Elige un profesional del centro que pueda ser referencia.');
            app(AsignacionReferenciaService::class)->cambiarManual($pendiente->historia, $profesional, $this->motivo, $supervisor, $this->centro);

            return;
        }

        throw new LogicException('Esta acción no se puede aplicar a esta entrada.');
    }

    /**
     * Entradas abiertas de un tipo del alcance del centro.
     *
     * @param TipoAsignacionPendiente $tipo
     * @return EloquentCollection<int, AsignacionPendiente>
     */
    private function pendientesDe(TipoAsignacionPendiente $tipo): EloquentCollection
    {
        return $this->centro ? app(BandejaAsignacionesService::class)->pendientes($this->centro, $tipo) : new EloquentCollection();
    }

    /**
     * Carga una entrada abierta solo si es del alcance del centro.
     *
     * @param int $id
     * @return AsignacionPendiente|null
     */
    private function cargarPendiente(int $id): ?AsignacionPendiente
    {
        return $this->centro ? app(BandejaAsignacionesService::class)->pendiente($this->centro, $id) : null;
    }

    /**
     * Olvida las listas calculadas para que la vista refleje la decisión.
     *
     * @return void
     */
    private function limpiarCache(): void
    {
        unset($this->sinCentro, $this->sinReferencia, $this->cambiosDomicilio, $this->repartos, $this->actividad);
    }
}

<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\CatalogoSistema;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use LogicException;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Enums\EstadoSlot;
use Modules\Agenda\Enums\HerramientaCita;
use Modules\Agenda\Enums\UrgenciaCita;
use Modules\Agenda\Livewire\Citas\Concerns\BuscaPersonas;
use Modules\Agenda\Models\Cita;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\Slot;
use Modules\Agenda\Services\Citas\AtencionCitaService;
use Modules\Agenda\Services\Citas\EnlaceAtencionCita;

/**
 * Agenda del profesional en el interfaz operativo de Intervención: sus citas y
 * los eventos a los que está convocado, en vista de día, semana o mes.
 *
 * Sobre cada cita propia ofrece *Atender* (abre la ficha con la herramienta del
 * tipo de cita y la cita enlazada), *Incomparecencia*, *Acompañantes* y *Pedir
 * cambio* al supervisor (docs/modulo-citas.md §3.5, §3.7 y §7). No ofrece
 * reprogramar ni cancelar: las citas de la propia agenda solo las mueve quien
 * gestiona las del centro (RN-05). Todas las acciones pasan por
 * AtencionCitaService, que comprueba la política y deja el evento.
 *
 * @property-read string $tituloFecha
 * @property-read array<string, list<array<string, mixed>>> $citasDia
 * @property-read array<string, list<array<string, mixed>>> $citasSemana
 * @property-read array<int, array{fecha: string, citas: int, tipos: array<string, int>}> $datosMes
 * @property-read array{alertas_sin_reconocer: int, seguimientos_vencidos: int, citas: int, mensajes_sin_leer: int} $kpis
 * @property-read array<string, list<string>> $huecosLibres
 * @property-read Cita|null $citaEnAccion
 * @property-read array<string, string> $relacionesAcompanante
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega1.md §4
 */
#[Layout('layouts.operativo')]
class AgendaPage extends Component
{
    use BuscaPersonas;

    /** Estados de cita que se pintan en la agenda (las movidas o anuladas no ocupan el hueco). */
    private const ESTADOS_VISIBLES = [
        EstadoCita::Confirmada,
        EstadoCita::Completada,
        EstadoCita::NoShowCiudadano,
        EstadoCita::NoShowProfesional,
    ];

    /** @var string Vista activa: 'dia' | 'semana' | 'mes' */
    public string $vista = 'dia';

    /** @var string Fecha de referencia de navegación (ISO 8601) */
    public string $fechaAncla;

    /** @var int|null Cita sobre la que está abierto un formulario. */
    public ?int $citaAccionId = null;

    /** @var string|null Formulario abierto: 'acompanantes' o 'cambio'. */
    public ?string $accion = null;

    /** @var array{relacion: string, ciudadano_id: int|null, ciudadano_nombre: string, nombre: string} Acompañante que se registra. */
    public array $formAcompanante = ['relacion' => '', 'ciudadano_id' => null, 'ciudadano_nombre' => '', 'nombre' => ''];

    /** @var string Qué cambio se pide al supervisor. */
    public string $textoCambio = '';

    /** @var string|null Resultado de la última acción. */
    public ?string $aviso = null;

    /**
     * Inicializa la fecha ancla al día de hoy.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->fechaAncla = today()->toDateString();
    }

    // -------------------------------------------------------------------------
    // Navegación
    // -------------------------------------------------------------------------

    /**
     * Retrocede 1 día, semana o mes según la vista activa.
     *
     * @return void
     */
    public function navegarAnterior(): void
    {
        $fecha = Carbon::parse($this->fechaAncla);
        $this->fechaAncla = match ($this->vista) {
            'dia' => $fecha->subDay()->toDateString(),
            'semana' => $fecha->subWeek()->toDateString(),
            'mes' => $fecha->subMonth()->toDateString(),
            default => $this->fechaAncla,
        };
    }

    /**
     * Avanza 1 día, semana o mes según la vista activa.
     *
     * @return void
     */
    public function navegarSiguiente(): void
    {
        $fecha = Carbon::parse($this->fechaAncla);
        $this->fechaAncla = match ($this->vista) {
            'dia' => $fecha->addDay()->toDateString(),
            'semana' => $fecha->addWeek()->toDateString(),
            'mes' => $fecha->addMonth()->toDateString(),
            default => $this->fechaAncla,
        };
    }

    /**
     * Resetea la fecha ancla al día de hoy.
     *
     * @return void
     */
    public function irAHoy(): void
    {
        $this->fechaAncla = today()->toDateString();
    }

    /**
     * Cambia la vista activa.
     *
     * @param string $vista 'dia' | 'semana' | 'mes'
     * @return void
     */
    public function setVista(string $vista): void
    {
        $this->vista = $vista;
    }

    /**
     * Al hacer clic en un día en la vista de mes, navega a la vista de día.
     *
     * @param string $fecha ISO 8601
     * @return void
     */
    public function irADia(string $fecha): void
    {
        $this->fechaAncla = $fecha;
        $this->setVista('dia');
    }

    // -------------------------------------------------------------------------
    // Propiedades computadas
    // -------------------------------------------------------------------------

    /**
     * Título descriptivo de la fecha según la vista activa.
     *
     * @return string
     */
    #[Computed]
    public function tituloFecha(): string
    {
        $fecha = Carbon::parse($this->fechaAncla)->locale('es');

        return match ($this->vista) {
            'dia' => $fecha->isoFormat('dddd, D [de] MMMM [de] YYYY'),
            'semana' => $fecha->startOfWeek()->isoFormat('D MMM').' – '.$fecha->endOfWeek()->isoFormat('D MMM YYYY'),
            'mes' => $fecha->isoFormat('MMMM [de] YYYY'),
            default => '',
        };
    }

    /**
     * Citas y eventos para la vista de día: 4 columnas (ayer, hoy, mañana, pasado mañana).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function citasDia(): array
    {
        $ancla = Carbon::parse($this->fechaAncla);

        return $this->entradasPorDia($ancla->copy()->subDay(), $ancla->copy()->addDays(2));
    }

    /**
     * Citas y eventos para la vista de semana: lunes a viernes de la semana del ancla.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function citasSemana(): array
    {
        $lunes = Carbon::parse($this->fechaAncla)->startOfWeek();

        return $this->entradasPorDia($lunes, $lunes->copy()->addDays(4));
    }

    /**
     * Datos del mes para la vista de mes: número de citas y tipos por día.
     *
     * @return array<int, array{fecha: string, citas: int, tipos: array<string, int>}>
     */
    #[Computed]
    public function datosMes(): array
    {
        $inicio = Carbon::parse($this->fechaAncla)->startOfMonth();
        $entradas = $this->entradasPorDia($inicio, $inicio->copy()->endOfMonth());
        $datos = [];

        foreach ($entradas as $fecha => $citas) {
            $datos[(int) Carbon::parse($fecha)->day] = [
                'fecha' => $fecha,
                'citas' => count($citas),
                'tipos' => collect($citas)->countBy('tipo')->all(),
            ];
        }

        return $datos;
    }

    /**
     * Huecos libres del profesional en los días de la vista de día (hora de inicio).
     *
     * @return array<string, list<string>>
     */
    #[Computed]
    public function huecosLibres(): array
    {
        $ancla = Carbon::parse($this->fechaAncla);

        return Slot::where('usuario_id', Auth::id())
            ->whereBetween('fecha', [$ancla->copy()->subDay()->toDateString(), $ancla->copy()->addDays(2)->toDateString()])
            ->where('estado', EstadoSlot::Disponible)
            ->orderBy('hora_inicio')
            ->get(['fecha', 'hora_inicio'])
            ->groupBy(fn (Slot $s) => $s->fecha->toDateString())
            ->map(fn (Collection $slots) => $slots->map(fn (Slot $s) => substr((string) $s->hora_inicio, 0, 5))->unique()->values()->all())
            ->all();
    }

    /**
     * KPIs para la franja superior de la vista.
     *
     * @return array{alertas_sin_reconocer: int, seguimientos_vencidos: int, citas: int, mensajes_sin_leer: int}
     */
    #[Computed]
    public function kpis(): array
    {
        return [
            'alertas_sin_reconocer' => $this->contarAlertasPendientes(),
            'seguimientos_vencidos' => $this->contarSeguimientosVencidos(),
            'citas' => $this->contarCitasRango(),
            'mensajes_sin_leer' => $this->contarMensajesSinLeer(),
        ];
    }

    /**
     * Cita propia sobre la que hay un formulario abierto.
     *
     * @return Cita|null
     */
    #[Computed]
    public function citaEnAccion(): ?Cita
    {
        return $this->citaAccionId ? $this->citaPropia($this->citaAccionId) : null;
    }

    /**
     * Relaciones posibles de un acompañante (catálogo del sistema).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function relacionesAcompanante(): array
    {
        return CatalogoSistema::opcionesParaSelect('cita.relacion_acompanante');
    }

    // -------------------------------------------------------------------------
    // Acciones sobre las citas propias
    // -------------------------------------------------------------------------

    /**
     * Marca que la persona no vino a la cita.
     *
     * @param int $citaId
     * @return void
     */
    public function marcarIncomparecencia(int $citaId): void
    {
        $this->ejecutar(
            fn () => app(AtencionCitaService::class)->registrarNoShow($this->citaPropia($citaId), Auth::user()),
            'Incomparecencia registrada.',
        );
    }

    /**
     * Abre el formulario de acompañantes o de petición de cambio de una cita.
     *
     * @param int $citaId
     * @param string $accion 'acompanantes' o 'cambio'.
     * @return void
     */
    public function abrirAccion(int $citaId, string $accion): void
    {
        $this->cerrarAccion();
        $this->citaAccionId = $this->citaPropia($citaId)->id;
        $this->accion = $accion === 'cambio' ? 'cambio' : 'acompanantes';
    }

    /**
     * Cierra el formulario abierto.
     *
     * @return void
     */
    public function cerrarAccion(): void
    {
        $this->reset('citaAccionId', 'accion', 'formAcompanante', 'textoCambio');
        $this->limpiarBusquedaPersona();
        $this->resetErrorBag();
        unset($this->citaEnAccion);
    }

    /**
     * Enlaza el acompañante con una persona que ya está en VIDA.
     *
     * @param int $ciudadanoId
     * @return void
     */
    public function elegirAcompanante(int $ciudadanoId): void
    {
        $persona = collect($this->personas ?? [])->firstWhere('id', $ciudadanoId);

        if ($persona !== null) {
            $this->formAcompanante['ciudadano_id'] = $ciudadanoId;
            $this->formAcompanante['ciudadano_nombre'] = $persona['nombre'];
            $this->formAcompanante['nombre'] = '';
            $this->limpiarBusquedaPersona();
        }
    }

    /**
     * Quita la persona enlazada para escribir el nombre a mano.
     *
     * @return void
     */
    public function quitarAcompananteEnlazado(): void
    {
        $this->formAcompanante['ciudadano_id'] = null;
        $this->formAcompanante['ciudadano_nombre'] = '';
    }

    /**
     * Registra quién acompañó a la persona. Solo registro: no crea citas ni vínculos.
     *
     * @return void
     */
    public function guardarAcompanante(): void
    {
        $cita = $this->citaEnAccion;
        abort_if($cita === null, 404);

        $acompanante = [
            'relacion' => $this->formAcompanante['relacion'],
            'ciudadano_id' => $this->formAcompanante['ciudadano_id'] ?: null,
            'nombre' => $this->formAcompanante['nombre'] ?: null,
        ];

        $this->ejecutar(
            fn () => app(AtencionCitaService::class)->registrarAcompanantes($cita, [$acompanante], Auth::user()),
            'Acompañante registrado.',
            'acompanante',
        );
    }

    /**
     * Pide al supervisor del centro un cambio en la cita (le llega un mensaje con enlace).
     *
     * @return void
     */
    public function pedirCambio(): void
    {
        $cita = $this->citaEnAccion;
        abort_if($cita === null, 404);

        $this->ejecutar(
            fn () => app(AtencionCitaService::class)->solicitarCambio($cita, $this->textoCambio, Auth::user()),
            'Petición de cambio enviada al supervisor.',
            'cambio',
        );
    }

    // -------------------------------------------------------------------------
    // Consultas KPI — se conectarán con los módulos reales progresivamente
    // -------------------------------------------------------------------------

    /**
     * @return int
     */
    private function contarAlertasPendientes(): int
    {
        // TODO: conectar con módulo Mensajes cuando esté disponible
        return 0;
    }

    /**
     * @return int
     */
    private function contarSeguimientosVencidos(): int
    {
        // TODO: conectar con módulo Intervencion (planes activos con seguimiento vencido)
        return 0;
    }

    /**
     * Citas propias (sin eventos) en el periodo de la vista activa.
     *
     * @return int
     */
    private function contarCitasRango(): int
    {
        $ancla = Carbon::parse($this->fechaAncla);
        [$desde, $hasta] = match ($this->vista) {
            'semana' => [$ancla->copy()->startOfWeek(), $ancla->copy()->endOfWeek()],
            'mes' => [$ancla->copy()->startOfMonth(), $ancla->copy()->endOfMonth()],
            default => [$ancla, $ancla],
        };

        return Cita::where('profesional_id', Auth::id())
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estado', self::ESTADOS_VISIBLES)
            ->count();
    }

    /**
     * @return int
     */
    private function contarMensajesSinLeer(): int
    {
        // TODO: conectar con módulo Mensajes cuando esté disponible
        return 0;
    }

    // -------------------------------------------------------------------------
    // Datos de la agenda
    // -------------------------------------------------------------------------

    /**
     * Citas del profesional y eventos a los que está convocado, por día (todos
     * los días del rango, aunque estén vacíos) y por hora.
     *
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return array<string, list<array<string, mixed>>>
     */
    private function entradasPorDia(Carbon $desde, Carbon $hasta): array
    {
        $usuario = Auth::user();
        $rango = [$desde->toDateString(), $hasta->toDateString()];

        $citas = Cita::with(['ciudadano', 'tipoCita', 'solicitud'])
            ->where('profesional_id', $usuario->id)
            ->whereBetween('fecha', $rango)
            ->whereIn('estado', self::ESTADOS_VISIBLES)
            ->get()
            ->map(fn (Cita $c) => $this->entradaDeCita($c));

        $eventos = EventoAgenda::delProfesional($usuario->id)
            ->whereBetween('fecha', $rango)
            ->get()
            ->map(fn (EventoAgenda $e) => [
                'clave' => 'evento-'.$e->id,
                'es_cita' => false,
                'id' => $e->id,
                'fecha' => $e->fecha->toDateString(),
                'hora' => substr((string) $e->hora_inicio, 0, 5),
                'tipo' => 'evento',
                'titulo' => $e->titulo,
                'subtitulo' => null,
            ]);

        $porDia = $citas->concat($eventos)->sortBy('hora')->groupBy('fecha');
        $dias = [];

        for ($dia = $desde->copy(); $dia->lte($hasta); $dia->addDay()) {
            $dias[$dia->toDateString()] = $porDia->get($dia->toDateString(), collect())->values()->all();
        }

        return $dias;
    }

    /**
     * Lo que la agenda pinta de una cita y las acciones que el usuario tiene sobre ella.
     *
     * @param Cita $cita
     * @return array<string, mixed>
     */
    private function entradaDeCita(Cita $cita): array
    {
        $usuario = Auth::user();
        $abierta = $cita->estado === EstadoCita::Confirmada;
        $atiende = $usuario->can('atender', $cita);

        return [
            'clave' => 'cita-'.$cita->id,
            'es_cita' => true,
            'id' => $cita->id,
            'fecha' => $cita->fecha->toDateString(),
            'hora' => substr((string) $cita->hora_inicio, 0, 5),
            'tipo' => $this->tipoVisual($cita),
            'titulo' => $cita->pendienteDeIdentificar() ? 'Pendiente de identificar' : $cita->ciudadano?->nombre_completo,
            'subtitulo' => $cita->tipoCita->nombreParaUsuario($usuario),
            'estado' => $cita->estado,
            'pendiente_cierre' => $cita->pendiente_cierre,
            'pendiente_identificar' => $cita->pendienteDeIdentificar(),
            'url_atender' => $abierta && $atiende ? app(EnlaceAtencionCita::class)->url($cita) : null,
            'puede_incomparecencia' => $abierta && $atiende,
            'puede_acompanantes' => $atiende && in_array($cita->estado, [EstadoCita::Confirmada, EstadoCita::Completada], true),
            'puede_pedir_cambio' => $abierta && $usuario->can('pedirCambio', $cita),
        ];
    }

    /**
     * Clave de color de la cita en la agenda (App\Support\Ui\Tono vía Tonos::tipoCita).
     *
     * @param Cita $cita
     * @return string
     */
    private function tipoVisual(Cita $cita): string
    {
        return match (true) {
            $cita->solicitud?->urgencia === UrgenciaCita::Urgente => 'urgencia',
            $cita->tipoCita->herramienta === HerramientaCita::EntrevistaSeguimiento => 'seguimiento',
            default => 'entrevista',
        };
    }

    /**
     * Cita de la agenda del usuario (una ajena no existe para esta pantalla).
     *
     * @param int $citaId
     * @return Cita
     */
    private function citaPropia(int $citaId): Cita
    {
        return Cita::where('profesional_id', Auth::id())->findOrFail($citaId);
    }

    /**
     * Ejecuta una acción de servicio: si una regla del dominio la impide, lo
     * muestra en el campo de error; si va bien, deja el aviso y recarga la agenda.
     *
     * @param callable(): mixed $accion
     * @param string $ok
     * @param string $campoError
     * @return void
     */
    private function ejecutar(callable $accion, string $ok, string $campoError = 'agenda'): void
    {
        try {
            $accion();
        } catch (LogicException|InvalidArgumentException|AuthorizationException $e) {
            $this->addError($campoError, $e->getMessage());

            return;
        }

        $this->cerrarAccion();
        $this->aviso = $ok;
        unset($this->citasDia, $this->citasSemana, $this->datosMes, $this->kpis);
    }

    /**
     * Renderiza la pantalla de agenda.
     *
     * @return View
     */
    public function render(): View
    {
        return view('intervencion::livewire.agenda-page');
    }
}

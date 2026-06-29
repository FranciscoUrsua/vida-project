<?php

namespace Modules\Agenda\Livewire;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\EventoAgenda;
use Modules\Agenda\Models\ExcepcionProfesional;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Agenda\Services\CuadrantePublicadorService;
use Modules\Centro\Models\Centro;

/**
 * Componente Livewire para la pantalla de cuadrante mensual del supervisor.
 *
 * Muestra el cuadrante semana a semana, permite añadir eventos puntuales
 * y publicar el cuadrante cuando el supervisor lo aprueba.
 *
 * @property Centro $centro
 * @property int $anyo
 * @property int $mes
 * @property int $semanaActual
 * @property CuadranteMes|null $cuadrante
 * @property bool $modalEventoAbierto
 * @property array $eventoForm
 * @property int|null $eventoProf
 * @property int|null $eventoDay
 * @property bool $modalExcAbierto
 * @property array $excDetalle
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class CuadranteMesComponent extends Component
{
    /** @var Centro Centro del cuadrante */
    public Centro $centro;

    /** @var int Año del cuadrante */
    public int $anyo;

    /** @var int Mes del cuadrante (1-12) */
    public int $mes;

    /** @var int Semana visible actualmente (0-4) */
    public int $semanaActual = 0;

    /** @var CuadranteMes|null Cuadrante cargado */
    public ?CuadranteMes $cuadrante = null;

    /** @var bool Controla la visibilidad del modal de añadir evento */
    public bool $modalEventoAbierto = false;

    /**
     * Datos del formulario de nuevo evento.
     *
     * @var array<string, mixed>
     */
    public array $eventoForm = [];

    /** @var int|null ID del profesional para el que se abre el modal de evento */
    public ?int $eventoProf = null;

    /** @var int|null Día del mes para el que se abre el modal de evento */
    public ?int $eventoDay = null;

    /** @var bool Controla la visibilidad del modal de detalle de excepción */
    public bool $modalExcAbierto = false;

    /**
     * Detalle de la excepción mostrada en el modal.
     *
     * @var array<string, mixed>
     */
    public array $excDetalle = [];

    /**
     * Inicializa el componente verificando autorización y cargando el cuadrante.
     *
     * @param Centro $centro
     * @param int $anyo
     * @param int $mes
     * @return void
     */
    public function mount(Centro $centro, int $anyo, int $mes): void
    {
        $this->autorizarAcceso($centro);

        $this->centro = $centro;
        $this->anyo   = $anyo;
        $this->mes    = $mes;

        $this->cuadrante = CuadranteMes::where('centro_id', $centro->id)
            ->where('anyo', $anyo)
            ->where('mes', $mes)
            ->orderByRaw("CASE WHEN estado = 'borrador' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * Avanza a la semana siguiente (máximo 4).
     *
     * @return void
     */
    public function nextSemana(): void
    {
        if ($this->semanaActual < 4) {
            $this->semanaActual++;
        }
    }

    /**
     * Retrocede a la semana anterior (mínimo 0).
     *
     * @return void
     */
    public function prevSemana(): void
    {
        if ($this->semanaActual > 0) {
            $this->semanaActual--;
        }
    }

    /**
     * Salta directamente a una semana del mes.
     *
     * @param int $idx Índice de semana (0-4)
     * @return void
     */
    public function goSemana(int $idx): void
    {
        $this->semanaActual = max(0, min(4, $idx));
    }

    /**
     * Devuelve los días laborables de la semana actualmente visible.
     *
     * @return array<int, Carbon>
     */
    #[Computed]
    public function diasSeman(): array
    {
        $inicioMes = Carbon::create($this->anyo, $this->mes, 1)->startOfWeek(Carbon::MONDAY);
        $inicioSemana = $inicioMes->copy()->addWeeks($this->semanaActual);

        $dias = [];
        for ($i = 0; $i < 5; $i++) {
            $dia = $inicioSemana->copy()->addDays($i);
            if ($dia->month === $this->mes) {
                $dias[] = $dia;
            }
        }

        return $dias;
    }

    /**
     * Devuelve los datos de una celda del grid para un profesional y fecha.
     *
     * @param int $profId ID del profesional
     * @param Carbon $fecha Fecha de la celda
     * @return array{tipo_celda: string, excepcion: ExcepcionProfesional|null, eventos: array<int, EventoAgenda>, franjas: array<int, mixed>}
     */
    public function getCelda(int $profId, Carbon|string $fecha): array
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
        $excepcion = ExcepcionProfesional::where('usuario_id', $profId)
            ->where('centro_id', $this->centro->id)
            ->where('fecha_inicio', '<=', $fecha->toDateString())
            ->where('fecha_fin', '>=', $fecha->toDateString())
            ->first();

        if ($excepcion !== null) {
            return ['tipo_celda' => 'excepcion', 'excepcion' => $excepcion, 'eventos' => [], 'franjas' => []];
        }

        if (! $fecha->isWeekday()) {
            return ['tipo_celda' => 'no_laborable', 'excepcion' => null, 'eventos' => [], 'franjas' => []];
        }

        $eventos = EventoAgenda::where('centro_id', $this->centro->id)
            ->where('fecha', $fecha->toDateString())
            ->whereHas('profesionales', fn ($q) => $q->where('users.id', $profId))
            ->get()
            ->all();

        return ['tipo_celda' => 'normal', 'excepcion' => null, 'eventos' => $eventos, 'franjas' => []];
    }

    /**
     * Abre el modal para añadir un evento puntual.
     *
     * @param int $profId ID del profesional
     * @param int $dayNum Día del mes
     * @return void
     */
    public function abrirModalEvento(int $profId, int $dayNum): void
    {
        $this->eventoProf         = $profId;
        $this->eventoDay          = $dayNum;
        $this->eventoForm         = ['titulo' => '', 'hora_inicio' => '', 'hora_fin' => '', 'convocados' => [$profId], 'notas' => ''];
        $this->modalEventoAbierto = true;
    }

    /**
     * Persiste el evento puntual con origen 'director'.
     *
     * @return void
     */
    public function guardarEvento(): void
    {
        $this->validate([
            'eventoForm.titulo'      => ['required', 'string', 'max:255'],
            'eventoForm.hora_inicio' => ['required'],
            'eventoForm.hora_fin'    => ['required'],
        ]);

        $fecha = Carbon::create($this->anyo, $this->mes, $this->eventoDay);

        $evento = EventoAgenda::create([
            'centro_id'    => $this->centro->id,
            'tipo_evento'  => 'interno',
            'titulo'       => $this->eventoForm['titulo'],
            'fecha'        => $fecha->toDateString(),
            'hora_inicio'  => $this->eventoForm['hora_inicio'],
            'hora_fin'     => $this->eventoForm['hora_fin'],
            'origen'       => 'director',
            'creado_por_id'=> auth()->id(),
            'notas'        => $this->eventoForm['notas'] ?: null,
        ]);

        $convocados = (array) ($this->eventoForm['convocados'] ?? []);
        $evento->agregarProfesionales($convocados);

        $this->modalEventoAbierto = false;
        unset($this->diasSeman);
        $this->dispatch('toast', message: 'Evento añadido al cuadrante.', type: 'success');
    }

    /**
     * Abre el modal de detalle de excepción para una celda concreta.
     *
     * @param int $profId ID del profesional
     * @param int $dayNum Día del mes
     * @return void
     */
    public function abrirModalExc(int $profId, int $dayNum): void
    {
        $fecha = Carbon::create($this->anyo, $this->mes, $dayNum);

        $excepcion = ExcepcionProfesional::where('usuario_id', $profId)
            ->where('centro_id', $this->centro->id)
            ->where('fecha_inicio', '<=', $fecha->toDateString())
            ->where('fecha_fin', '>=', $fecha->toDateString())
            ->first();

        if ($excepcion !== null) {
            $this->excDetalle     = ['tipo' => $excepcion->tipo->value, 'id' => $excepcion->id, 'profesional_id' => $profId];
            $this->modalExcAbierto = true;
        }
    }

    /**
     * Publica el cuadrante en borrador y materializa los slots.
     *
     * @param string $notasEquipo Notas opcionales para el equipo
     * @return void
     */
    public function publicar(string $notasEquipo = ''): void
    {
        if ($this->cuadrante === null || $this->cuadrante->estado !== EstadoCuadrante::Borrador) {
            return;
        }

        app(CuadrantePublicadorService::class)->publicar($this->cuadrante, auth()->id());
        $this->cuadrante = $this->cuadrante->fresh();
        $this->dispatch('toast', message: 'Cuadrante publicado correctamente.', type: 'success');
    }

    /**
     * Devuelve métricas resumen del cuadrante del mes.
     *
     * @return array{dias_con_excepciones: int, slots_disponibles: int, eventos_internos: int, slots_afectados: int}
     */
    #[Computed]
    public function metricas(): array
    {
        $iniciMes  = Carbon::create($this->anyo, $this->mes, 1);
        $finMes    = $iniciMes->copy()->endOfMonth();

        $excepciones = ExcepcionProfesional::where('centro_id', $this->centro->id)
            ->where('fecha_inicio', '<=', $finMes->toDateString())
            ->where('fecha_fin', '>=', $iniciMes->toDateString())
            ->get();

        $diasConExcepciones = 0;
        $cursor = $iniciMes->copy();
        while ($cursor->lte($finMes)) {
            if ($cursor->isWeekday()) {
                foreach ($excepciones as $exc) {
                    if ($exc->fecha_inicio->lte($cursor) && $exc->fecha_fin->gte($cursor)) {
                        $diasConExcepciones++;
                        break;
                    }
                }
            }
            $cursor->addDay();
        }

        return [
            'dias_con_excepciones' => $diasConExcepciones,
            'slots_disponibles'    => 0,
            'eventos_internos'     => 0,
            'slots_afectados'      => 0,
        ];
    }

    /**
     * Devuelve las excepciones incorporadas automáticamente al cuadrante.
     *
     * @return Collection<int, ExcepcionProfesional>
     */
    #[Computed]
    public function excepcionesIncorporadas(): Collection
    {
        return ExcepcionProfesional::where('centro_id', $this->centro->id)
            ->where('fecha_inicio', '<=', Carbon::create($this->anyo, $this->mes)->endOfMonth()->toDateString())
            ->where('fecha_fin', '>=', Carbon::create($this->anyo, $this->mes, 1)->toDateString())
            ->where('afecta_disponibilidad', true)
            ->get();
    }

    /**
     * Renderiza la vista del cuadrante mensual.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.cuadrante-mes');
    }

    /**
     * Verifica que el usuario autenticado es supervisor asignado al centro dado.
     *
     * @param Centro $centro
     * @return void
     */
    private function autorizarAcceso(Centro $centro): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && $user->hasAnyRole(['supervision', 'adm_sistema']),
            403
        );

        // Supervisor solo puede gestionar centros de su UO
        if ($user->hasRole('supervision') && ! $user->hasRole('adm_sistema')) {
            $uoIds = $user->uosActivas()->pluck('id')->all();
            abort_unless(
                in_array($centro->unidad_organizativa_id, $uoIds, true),
                403
            );
        }
    }
}

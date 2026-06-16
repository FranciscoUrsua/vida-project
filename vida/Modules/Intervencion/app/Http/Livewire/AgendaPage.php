<?php

namespace Modules\Intervencion\Http\Livewire;

use App\Models\HistoriaSocial;
use App\Models\Scopes\AmbitoUoScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pantalla de Agenda del interfaz operativo de Intervención.
 *
 * Implementa tres vistas (día, semana, mes) con navegación de fechas.
 * Los datos de citas se obtienen del módulo Agenda cuando esté disponible;
 * hasta entonces se usa una fixture de desarrollo.
 *
 * @see docs/instrucciones-cli/ui-intervencion-entrega1.md §4
 */
#[Layout('layouts.operativo')]
class AgendaPage extends Component
{
    /** @var string Vista activa: 'dia' | 'semana' | 'mes' */
    public string $vista = 'dia';

    /** @var string Fecha de referencia de navegación (ISO 8601) */
    public string $fechaAncla;

    /**
     * Inicializa la fecha ancla al día de hoy.
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
     */
    public function irAHoy(): void
    {
        $this->fechaAncla = today()->toDateString();
    }

    /**
     * Cambia la vista activa.
     *
     * @param string $vista 'dia' | 'semana' | 'mes'
     */
    public function setVista(string $vista): void
    {
        $this->vista = $vista;
    }

    /**
     * Al hacer clic en un día en la vista de mes, navega a la vista de día.
     *
     * @param string $fecha ISO 8601
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
     * Citas para la vista de día: 4 columnas (ayer, hoy, mañana, pasado mañana).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    #[Computed]
    public function citasDia(): array
    {
        // TODO: reemplazar con consulta real al módulo Agenda cuando esté disponible
        $ancla = Carbon::parse($this->fechaAncla);
        $columnas = [];
        for ($offset = -1; $offset <= 2; $offset++) {
            $fecha = $ancla->copy()->addDays($offset)->toDateString();
            $columnas[$fecha] = $this->citasFixture($fecha);
        }

        return $columnas;
    }

    /**
     * Citas para la vista de semana: lunes a viernes de la semana del ancla.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    #[Computed]
    public function citasSemana(): array
    {
        // TODO: reemplazar con consulta real al módulo Agenda cuando esté disponible
        $lunes = Carbon::parse($this->fechaAncla)->startOfWeek();
        $columnas = [];
        for ($i = 0; $i < 5; $i++) {
            $fecha = $lunes->copy()->addDays($i)->toDateString();
            $columnas[$fecha] = $this->citasFixture($fecha);
        }

        return $columnas;
    }

    /**
     * Datos del mes para la vista de mes: número de citas y tipos por día.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function datosMes(): array
    {
        // TODO: reemplazar con consulta real al módulo Agenda cuando esté disponible
        $inicio = Carbon::parse($this->fechaAncla)->startOfMonth();
        $diasEnMes = $inicio->daysInMonth;
        $datos = [];
        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fecha = $inicio->copy()->day($dia)->toDateString();
            $citas = $this->citasFixture($fecha);
            $tipos = collect($citas)
                ->groupBy('tipo')
                ->map(fn ($grupo) => count($grupo))
                ->toArray();
            arsort($tipos); // urgencias primero si están definidas
            $datos[$dia] = [
                'fecha' => $fecha,
                'citas' => count($citas),
                'tipos' => $tipos,
            ];
        }

        return $datos;
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

    // -------------------------------------------------------------------------
    // Consultas KPI — se conectarán con los módulos reales progresivamente
    // -------------------------------------------------------------------------

    private function contarAlertasPendientes(): int
    {
        // TODO: conectar con módulo Mensajes cuando esté disponible
        return 0;
    }

    private function contarSeguimientosVencidos(): int
    {
        // TODO: conectar con módulo Intervencion (planes activos con seguimiento vencido)
        return 0;
    }

    private function contarCitasRango(): int
    {
        // TODO: conectar con módulo Agenda cuando esté disponible
        return 0;
    }

    private function contarMensajesSinLeer(): int
    {
        // TODO: conectar con módulo Mensajes cuando esté disponible
        return 0;
    }

    // -------------------------------------------------------------------------
    // Fixture de desarrollo
    // -------------------------------------------------------------------------

    /**
     * Genera citas de ejemplo para una fecha dada.
     * Determinista: la misma fecha siempre devuelve las mismas citas.
     *
     * TODO: reemplazar con consulta real al módulo Agenda cuando esté disponible
     *
     * @param string $fecha ISO 8601
     *
     * @return array<int, array<string, mixed>>
     */
    private function citasFixture(string $fecha): array
    {
        $tipos = ['entrevista', 'seguimiento', 'urgencia', 'evento'];
        $ciudadanos = ['María García López', 'Juan Pérez Martín', 'Ana Ruiz Sánchez', 'Carlos Díaz Fernández'];
        $hash = abs(crc32($fecha));
        $count = $hash % 3;

        // Obtener ejemplos de historias sociales del profesional para los enlaces.
        // Incluye ciudadano_id para poder enlazar a ficha cuando no hay historia.
        $historias = [];
        if (Auth::user()?->profesional_id) {
            $historias = HistoriaSocial::withoutGlobalScope(AmbitoUoScope::class)
                ->whereNotNull('ciudadano_id')
                ->limit(5)
                ->get(['id', 'ciudadano_id'])
                ->toArray();
        }

        $citas = [];
        for ($i = 0; $i < $count; $i++) {
            $tipo = $tipos[($hash + $i) % count($tipos)];
            // Los eventos no tienen ciudadano; el resto usa datos de historia si existe
            $historiaEntry = ($tipo !== 'evento') ? ($historias[$i] ?? null) : null;

            $citas[] = [
                'id' => $hash + $i,
                'hora' => sprintf('%02d:00', 9 + ($i * 2)),
                'duracion' => 60,
                'ciudadano' => $ciudadanos[$i % count($ciudadanos)],
                'historia_id' => $historiaEntry ? $historiaEntry['id'] : null,
                'ciudadano_id' => $historiaEntry ? $historiaEntry['ciudadano_id'] : null,
                'tipo' => $tipo,
                'subtipo' => null,
                'fecha' => $fecha,
            ];
        }

        return $citas;
    }

    public function render(): View
    {
        return view('intervencion::livewire.agenda-page');
    }
}

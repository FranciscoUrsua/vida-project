<?php

namespace Modules\Agenda\Livewire;

use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCuadrante;
use Modules\Agenda\Models\CuadranteMes;
use Modules\Agenda\Models\HorarioCentro;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Componente Livewire para definir la semana tipo del centro.
 *
 * Permite al supervisor configurar las franjas horarias estándar de cada día
 * laborable. La semana tipo se almacena en HorarioCentro.semana_tipo como JSON
 * con clave 'base' para el patrón común y claves numéricas ('1'-'5') para días con variación.
 *
 * @property Centro $centro
 * @property array $semana
 * @property string $estado
 * @property bool $avisoBorrador
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class SemanaTypoComponent extends Component
{
    /** @var Centro Centro al que pertenece la semana tipo */
    public Centro $centro;

    /** @var array Estructura editable: claves 'base' y/o '1'-'5' con arrays de franjas */
    public array $semana = [];

    /** @var string Estado de la operación: '' | 'guardado' | 'error' */
    public string $estado = '';

    /** @var bool true si hay un CuadranteMes en borrador al guardar */
    public bool $avisoBorrador = false;

    /**
     * Inicializa el componente con el centro y carga la semana tipo vigente.
     *
     * @param Centro $centro Centro del supervisor
     * @return void
     */
    public function mount(Centro $centro): void
    {
        $this->centro = $centro;
        $this->autorizarAcceso();

        $horario = $this->horarioActivo();
        if ($horario !== null) {
            $this->semana = $horario->semana_tipo ?? [];
        }
    }

    /**
     * Persiste la semana tipo en HorarioCentro.semana_tipo.
     *
     * Valida coherencia temporal de las franjas. Si hay un cuadrante en borrador
     * para el mes siguiente, activa el aviso correspondiente.
     *
     * @return void
     */
    public function guardar(): void
    {
        if (! $this->validarFranjas()) {
            return;
        }

        $horario = $this->horarioActivo();
        if ($horario === null) {
            $this->estado = 'error';
            return;
        }

        $horario->update(['semana_tipo' => $this->semana]);

        $nextMonth = now()->addMonth();
        $this->avisoBorrador = CuadranteMes::where('centro_id', $this->centro->id)
            ->where('anyo', $nextMonth->year)
            ->where('mes', $nextMonth->month)
            ->where('estado', EstadoCuadrante::Borrador->value)
            ->exists();

        $this->estado = 'guardado';
        $this->dispatch('toast', message: 'Semana tipo guardada correctamente.', type: 'success');
    }

    /**
     * Replica las franjas del día origen en los días destino.
     *
     * @param int $diaOrigen Día a copiar (1=lunes…5=viernes)
     * @param array<int> $diasDestino Días de destino
     * @return void
     */
    public function copiarDia(int $diaOrigen, array $diasDestino): void
    {
        $franjas = $this->semana[(string) $diaOrigen] ?? $this->semana['base'] ?? [];
        foreach ($diasDestino as $dia) {
            $this->semana[(string) $dia] = $franjas;
        }
    }

    /**
     * Devuelve el número de slots de atención ciudadana estimados por día.
     *
     * Calcula minutos de franjas tipo 'atencion' del día (base o sobreescritura),
     * divide entre 30 (duración de cita) y multiplica por el número de profesionales activos.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function slotsEstimados(): array
    {
        $numProf = PerfilHorarioProfesional::where('centro_id', $this->centro->id)
            ->where('activo', true)
            ->count();

        $resultado = [];
        for ($dia = 1; $dia <= 5; $dia++) {
            $franjas = $this->semana[(string) $dia] ?? $this->semana['base'] ?? [];
            $minutosAtencion = 0;
            foreach ($franjas as $franja) {
                if (($franja['tipo'] ?? '') === 'atencion') {
                    $ini = $this->toMinutos($franja['inicio'] ?? '00:00');
                    $fin = $this->toMinutos($franja['fin'] ?? '00:00');
                    if ($fin > $ini) {
                        $minutosAtencion += $fin - $ini;
                    }
                }
            }
            $resultado[$dia] = (int) floor($minutosAtencion / 30) * $numProf;
        }

        return $resultado;
    }

    /**
     * Renderiza la vista del componente.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.semana-typo');
    }

    /**
     * Obtiene el HorarioCentro activo y vigente para el centro.
     *
     * @return HorarioCentro|null
     */
    private function horarioActivo(): ?HorarioCentro
    {
        return HorarioCentro::where('centro_id', $this->centro->id)
            ->activos()
            ->vigentes()
            ->orderByDesc('vigente_desde')
            ->first();
    }

    /**
     * Valida que ninguna franja tenga hora_fin anterior o igual a hora_inicio.
     *
     * @return bool false si hay alguna franja inválida
     */
    private function validarFranjas(): bool
    {
        foreach ($this->semana as $clave => $franjas) {
            foreach ($franjas as $idx => $franja) {
                $ini = $this->toMinutos($franja['inicio'] ?? '00:00');
                $fin = $this->toMinutos($franja['fin'] ?? '00:00');
                if ($fin <= $ini) {
                    $this->addError("semana.{$clave}.{$idx}.fin", 'La hora de fin debe ser posterior a la hora de inicio.');
                    $this->estado = 'error';
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Verifica que el usuario autenticado es supervisor asignado al centro.
     *
     * @return void
     */
    private function autorizarAcceso(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['supervision', 'adm_sistema']) ?? false,
            403
        );
    }

    /**
     * Convierte "HH:MM" a minutos desde medianoche.
     *
     * @param string $time
     * @return int
     */
    private function toMinutos(string $time): int
    {
        [$h, $m] = explode(':', $time);
        return (int) $h * 60 + (int) $m;
    }
}

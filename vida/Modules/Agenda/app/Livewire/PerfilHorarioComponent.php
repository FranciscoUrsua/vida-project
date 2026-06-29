<?php

namespace Modules\Agenda\Livewire;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Agenda\Models\PerfilHorarioProfesional;
use Modules\Centro\Models\Centro;

/**
 * Componente Livewire para gestionar el perfil horario de un profesional en un centro.
 *
 * Permite al supervisor configurar los días activos, franjas de mañana/tarde y
 * la jornada semanal. Al guardar, versiona el perfil si cambia la fecha de vigencia.
 *
 * @property User $profesional
 * @property Centro $centro
 * @property array $diasActivos
 * @property array $franjasPorDia
 * @property float $jornadaSemanal
 * @property string $vigenteDesde
 * @property string $notas
 */
#[Layout('agenda::layouts.agenda-supervisor')]
class PerfilHorarioComponent extends Component
{
    /** @var User Profesional cuyo perfil se gestiona */
    public User $profesional;

    /** @var Centro Centro al que aplica el perfil */
    public Centro $centro;

    /** @var array<int> Días de la semana activos (1=lun…5=vie) */
    public array $diasActivos = [1, 2, 3, 4, 5];

    /**
     * Franjas horarias por día.
     *
     * Estructura: [diaN => ['mIni' => '09:00', 'mFin' => '14:00', 'tIni' => null, 'tFin' => null]]
     *
     * @var array<int|string, array<string, string|null>>
     */
    public array $franjasPorDia = [];

    /** @var float Horas semanales de jornada */
    public float $jornadaSemanal = 35;

    /** @var string Fecha de vigencia del perfil (Y-m-d) */
    public string $vigenteDesde = '';

    /** @var string Notas informativas sobre el perfil */
    public string $notas = '';

    /**
     * Inicializa el componente cargando el perfil activo del profesional.
     *
     * @param User $profesional
     * @param Centro $centro
     * @return void
     */
    public function mount(User $profesional, Centro $centro): void
    {
        $this->profesional = $profesional;
        $this->centro = $centro;

        $perfil = PerfilHorarioProfesional::where('usuario_id', $profesional->id)
            ->where('centro_id', $centro->id)
            ->where('activo', true)
            ->first();

        if ($perfil !== null) {
            $this->jornadaSemanal = (float) $perfil->jornada_semanal_horas;
            $this->vigenteDesde   = $perfil->vigente_desde->toDateString();
            $this->notas          = $perfil->notas ?? '';
            $this->diasActivos    = array_map('intval', array_keys($perfil->horario_habitual ?? []));
            $this->franjasPorDia  = $this->parsearHorario($perfil->horario_habitual ?? []);
        } else {
            $this->vigenteDesde = now()->toDateString();
            $this->inicializarFranjasPorDefecto();
        }
    }

    /**
     * Activa o desactiva un día de la semana en el perfil.
     *
     * @param int $dia Número de día (1=lun…5=vie)
     * @return void
     */
    public function toggleDia(int $dia): void
    {
        if (in_array($dia, $this->diasActivos, true)) {
            $this->diasActivos = array_values(array_filter($this->diasActivos, fn ($d) => $d !== $dia));
        } else {
            $this->diasActivos[] = $dia;
            sort($this->diasActivos);
            if (! isset($this->franjasPorDia[$dia])) {
                $this->franjasPorDia[$dia] = ['mIni' => '09:00', 'mFin' => '14:00', 'tIni' => null, 'tFin' => null];
            }
        }
    }

    /**
     * Añade franja vespertina al día indicado con valores por defecto.
     *
     * @param int $dia Número de día (1=lun…5=vie)
     * @return void
     */
    public function addTarde(int $dia): void
    {
        $this->franjasPorDia[$dia]['tIni'] = '15:00';
        $this->franjasPorDia[$dia]['tFin'] = '19:00';
    }

    /**
     * Elimina la franja vespertina del día indicado.
     *
     * @param int $dia Número de día (1=lun…5=vie)
     * @return void
     */
    public function removeTarde(int $dia): void
    {
        $this->franjasPorDia[$dia]['tIni'] = null;
        $this->franjasPorDia[$dia]['tFin'] = null;
    }

    /**
     * Persiste el perfil horario.
     *
     * Si la fecha de vigencia coincide con el perfil activo, lo actualiza.
     * Si es diferente, cierra el anterior y crea uno nuevo.
     *
     * @return void
     */
    public function guardar(): void
    {
        $this->validate([
            'jornadaSemanal' => ['required', 'numeric', 'min:0'],
            'vigenteDesde'   => ['required', 'date'],
        ]);

        $perfilActual = PerfilHorarioProfesional::where('usuario_id', $this->profesional->id)
            ->where('centro_id', $this->centro->id)
            ->where('activo', true)
            ->first();

        $horario = $this->construirHorarioHabitual();

        if ($perfilActual !== null && $perfilActual->vigente_desde->toDateString() === $this->vigenteDesde) {
            $perfilActual->update([
                'jornada_semanal_horas' => $this->jornadaSemanal,
                'horario_habitual'      => $horario,
                'notas'                 => $this->notas ?: null,
            ]);
        } else {
            if ($perfilActual !== null) {
                $hasta = Carbon::parse($this->vigenteDesde)->subDay()->toDateString();
                $perfilActual->update(['vigente_hasta' => $hasta, 'activo' => false]);
            }

            PerfilHorarioProfesional::create([
                'usuario_id'            => $this->profesional->id,
                'centro_id'             => $this->centro->id,
                'jornada_semanal_horas' => $this->jornadaSemanal,
                'horario_habitual'      => $horario,
                'vigente_desde'         => $this->vigenteDesde,
                'vigente_hasta'         => null,
                'activo'                => true,
                'notas'                 => $this->notas ?: null,
            ]);
        }

        $this->dispatch('toast', message: 'Perfil horario guardado. Los cambios se aplicarán al generar el próximo cuadrante.', type: 'success');
        $this->dispatch('perfil-horario-guardado');
    }

    /**
     * Devuelve un resumen de la jornada y el horario principal del profesional.
     *
     * @return array{jornada: float, dias: int, horario_principal: string}
     */
    #[Computed]
    public function resumen(): array
    {
        $principal = $this->franjasPorDia[1] ?? $this->franjasPorDia[array_key_first($this->franjasPorDia) ?? 1] ?? [];

        return [
            'jornada'          => $this->jornadaSemanal,
            'dias'             => count($this->diasActivos),
            'horario_principal' => isset($principal['mIni'], $principal['mFin'])
                ? "{$principal['mIni']}–{$principal['mFin']}"
                : '',
        ];
    }

    /**
     * Renderiza la vista del componente.
     *
     * @return View
     */
    public function render(): View
    {
        return view('agenda::livewire.perfil-horario');
    }

    /**
     * Convierte horario_habitual de PerfilHorarioProfesional al formato interno del componente.
     *
     * @param array<string, array<int, array<string, string>>> $horario
     * @return array<int, array<string, string|null>>
     */
    private function parsearHorario(array $horario): array
    {
        $resultado = [];
        foreach ($horario as $dia => $franjas) {
            $m = $franjas[0] ?? [];
            $t = $franjas[1] ?? [];
            $resultado[(int) $dia] = [
                'mIni' => $m['inicio'] ?? '09:00',
                'mFin' => $m['fin']    ?? '14:00',
                'tIni' => $t['inicio'] ?? null,
                'tFin' => $t['fin']    ?? null,
            ];
        }
        return $resultado;
    }

    /**
     * Construye el array horario_habitual a partir del estado editable del componente.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function construirHorarioHabitual(): array
    {
        $horario = [];
        foreach ($this->diasActivos as $dia) {
            $f = $this->franjasPorDia[$dia] ?? ['mIni' => '09:00', 'mFin' => '14:00', 'tIni' => null, 'tFin' => null];
            $franjas = [['inicio' => $f['mIni'], 'fin' => $f['mFin']]];
            if (! empty($f['tIni']) && ! empty($f['tFin'])) {
                $franjas[] = ['inicio' => $f['tIni'], 'fin' => $f['tFin']];
            }
            $horario[(string) $dia] = $franjas;
        }
        return $horario;
    }

    /**
     * Inicializa las franjas con valores por defecto para los 5 días laborables.
     *
     * @return void
     */
    private function inicializarFranjasPorDefecto(): void
    {
        for ($d = 1; $d <= 5; $d++) {
            $this->franjasPorDia[$d] = ['mIni' => '09:00', 'mFin' => '14:00', 'tIni' => null, 'tFin' => null];
        }
    }
}

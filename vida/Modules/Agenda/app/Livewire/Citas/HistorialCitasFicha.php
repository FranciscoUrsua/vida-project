<?php

namespace Modules\Agenda\Livewire\Citas;

use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Agenda\Enums\EstadoCita;
use Modules\Agenda\Models\Cita;

/**
 * Historial de citas de la persona en su ficha, de cualquier centro (docs/modulo-citas.md §6 bis).
 *
 * Lo ve quien tiene `cita.ver_historial` y puede ver a la persona. Cada cita muestra
 * fecha y hora, tipo, centro, profesional, modalidad y estado; nunca el motivo de la
 * solicitud ni la intervención vinculada. El tipo sale con su etiqueta pública salvo
 * para quien lee la Historia Social (TipoCita::nombreParaUsuario()).
 *
 * Las reprogramadas se agrupan con la cita que las sustituye. Reprogramar y cancelar
 * llevan al detalle de la cita (CitaPage), que aplica las mismas policies: en su UO y
 * no las propias (RN-05 bis y RN-05 ter). Por defecto se muestran todas las futuras
 * y las 10 pasadas más recientes.
 *
 * @property-read Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}> $grupos
 * @property-read Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}> $futuras
 * @property-read Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}> $pasadas
 * @property-read int $pasadasOcultas
 */
class HistorialCitasFicha extends Component
{
    /** Citas pasadas que se muestran antes de «Ver más». */
    public const PASADAS_INICIALES = 10;

    /** Persona de la ficha. */
    #[Locked]
    public int $ciudadanoId;

    /** Si se muestran todas las pasadas. */
    public bool $todas = false;

    /**
     * Recibe la persona y comprueba que el usuario puede ver su historial.
     *
     * @param int $ciudadanoId Persona.
     *
     * @return void
     */
    public function mount(int $ciudadanoId): void
    {
        $this->ciudadanoId = $ciudadanoId;
        $this->autorizar();
    }

    /**
     * Grupos de citas: la cita vigente de cada cadena con las reprogramadas a las que sustituye.
     *
     * @return Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}>
     */
    #[Computed]
    public function grupos(): Collection
    {
        $citas = Cita::query()
            ->delCiudadano($this->ciudadanoId)
            ->with(['tipoCita', 'centro', 'profesional.profesional'])
            ->get()
            ->keyBy('id');

        // Cita que sustituye a cada una (cita_anterior_id apunta a la reprogramada)
        $sustituta = $citas->filter(fn (Cita $c): bool => $c->cita_anterior_id !== null)
            ->mapWithKeys(fn (Cita $c): array => [$c->cita_anterior_id => $c->id]);

        return $citas
            ->reject(fn (Cita $c): bool => $sustituta->has($c->id)) // solo la última de cada cadena
            ->map(function (Cita $cita) use ($citas): array {
                $anteriores = collect();
                $actual = $cita;
                while ($actual->cita_anterior_id !== null && $citas->has($actual->cita_anterior_id)) {
                    $actual = $citas->get($actual->cita_anterior_id);
                    $anteriores->push($actual);
                }

                return ['cita' => $cita, 'anteriores' => $anteriores];
            })
            ->values();
    }

    /**
     * Citas programadas (de ahora en adelante), de la más próxima a la más lejana.
     *
     * @return Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}>
     */
    #[Computed]
    public function futuras(): Collection
    {
        return $this->grupos
            ->filter(fn (array $g): bool => $this->momento($g['cita']) >= now()->format('Y-m-d H:i:s'))
            ->sortBy(fn (array $g): string => $this->momento($g['cita']))
            ->values();
    }

    /**
     * Citas pasadas, de la más reciente a la más antigua; las 10 primeras salvo «Ver más».
     *
     * @return Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}>
     */
    #[Computed]
    public function pasadas(): Collection
    {
        $pasadas = $this->todasLasPasadas();

        return $this->todas ? $pasadas : $pasadas->take(self::PASADAS_INICIALES);
    }

    /**
     * Número de citas pasadas que «Ver más» mostraría.
     *
     * @return int
     */
    #[Computed]
    public function pasadasOcultas(): int
    {
        return $this->todas ? 0 : max(0, $this->todasLasPasadas()->count() - self::PASADAS_INICIALES);
    }

    /**
     * Muestra todas las citas pasadas.
     *
     * @return void
     */
    public function verMas(): void
    {
        $this->autorizar();
        $this->todas = true;
        unset($this->pasadas, $this->pasadasOcultas);
    }

    /**
     * Si el usuario puede reprogramar o cancelar la cita (en su UO y no las propias).
     *
     * @param Cita $cita Cita.
     *
     * @return bool
     */
    public function gestionable(Cita $cita): bool
    {
        if ($cita->estado !== EstadoCita::Confirmada) {
            return false;
        }

        $usuario = Auth::user();

        return $usuario->can('reprogramar', $cita) || $usuario->can('cancelar', $cita);
    }

    /**
     * Renderiza el bloque (vuelve a comprobar el acceso en cada petición).
     *
     * @return View
     */
    public function render(): View
    {
        $this->autorizar();

        return view('agenda::livewire.citas.historial-citas-ficha', ['usuario' => Auth::user()]);
    }

    /**
     * Exige `cita.ver_historial` y poder ver a la persona (CiudadanoPolicy::view).
     *
     * @return void
     */
    private function autorizar(): void
    {
        /** @var User $usuario */
        $usuario = Auth::user();
        $ciudadano = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->findOrFail($this->ciudadanoId);

        abort_unless($usuario->can('cita.ver_historial') && Gate::allows('view', $ciudadano), 403);
    }

    /**
     * Todas las citas pasadas, de la más reciente a la más antigua.
     *
     * @return Collection<int, array{cita: Cita, anteriores: Collection<int, Cita>}>
     */
    private function todasLasPasadas(): Collection
    {
        return $this->grupos
            ->filter(fn (array $g): bool => $this->momento($g['cita']) < now()->format('Y-m-d H:i:s'))
            ->sortByDesc(fn (array $g): string => $this->momento($g['cita']))
            ->values();
    }

    /**
     * Fecha y hora de inicio de la cita, comparable como texto.
     *
     * @param Cita $cita Cita.
     *
     * @return string
     */
    private function momento(Cita $cita): string
    {
        // La hora puede venir como HH:MM o HH:MM:SS
        return $cita->fecha->format('Y-m-d').' '.str_pad(substr((string) $cita->hora_inicio, 0, 8), 8, ':00');
    }
}

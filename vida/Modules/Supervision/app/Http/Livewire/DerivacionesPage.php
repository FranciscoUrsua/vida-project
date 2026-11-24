<?php

namespace Modules\Supervision\Http\Livewire;

use App\Livewire\Concerns\OrdenaPorColumnas;
use App\Models\Ciudadano;
use App\Models\Scopes\AmbitoUoScope;
use App\Models\User;
use App\Services\PresentacionPersona;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Centro\Models\Centro;
use Modules\Centro\Services\Asignacion\CentroDeUsuario;
use Modules\Intervencion\Models\Derivacion;
use Modules\Intervencion\Services\DerivacionService;

/**
 * Bandeja de derivaciones del centro de destino (fase 3, B4.2).
 *
 * Lista las derivaciones pendientes del centro supervisado con persona, centro de
 * origen, TSR, tipo de plan, motivo y antigüedad, ordenables por cabecera (por defecto,
 * las más antiguas primero). Aceptan (asignando un profesional
 * de intervención del centro) o rechazan con motivo la dirección y la supervisión
 * adscritas al centro; la supervisión de UO superiores la ve, pero no resuelve.
 *
 * Personas protegidas (RN-19 de la addenda): su identidad solo la ve la supervisión
 * adscrita al centro de destino; el resto ve «Persona con protección especial».
 *
 * @property-read Centro|null $centro
 * @property-read bool $resuelve
 * @property-read Collection<int, array{derivacion: Derivacion, persona: string, origen: string, tsr: string, tipo: string, motivo: string, antiguedad: string}> $pendientes
 * @property-read Collection<int, User> $profesionales
 */
#[Layout('layouts.supervision')]
class DerivacionesPage extends Component
{
    use OrdenaPorColumnas;

    /** Derivación que se está aceptando. */
    public ?int $aceptarId = null;

    public ?int $responsableId = null;

    /** Derivación que se está rechazando. */
    public ?int $rechazarId = null;

    public string $motivoRechazo = '';

    public string $aviso = '';

    /**
     * Centro supervisado (el elegido en el selector de Supervisión), si lo supervisa.
     *
     * @return Centro|null
     */
    #[Computed]
    public function centro(): ?Centro
    {
        $usuario = auth()->user();
        $servicio = app(CentroDeUsuario::class);
        $centro = $servicio->centroSupervisado($usuario);

        return $centro !== null && $servicio->supervisa($usuario, $centro) ? $centro : null;
    }

    /**
     * Si el usuario resuelve las derivaciones del centro (adscrito a él).
     *
     * @return bool
     */
    #[Computed]
    public function resuelve(): bool
    {
        return $this->centro !== null && app(DerivacionService::class)->resuelveEn(auth()->user(), $this->centro);
    }

    /**
     * Derivaciones pendientes del centro, en el orden elegido (por defecto, las más antiguas primero).
     *
     * Se ordena en memoria sobre el texto que se pinta: la lista no se pagina y el
     * nombre de la persona está cifrado (o enmascarado, si es protegida).
     *
     * @return Collection<int, array{derivacion: Derivacion, persona: string, origen: string, tsr: string, tipo: string, motivo: string, antiguedad: string}>
     */
    #[Computed]
    public function pendientes(): Collection
    {
        if ($this->centro === null) {
            return collect();
        }

        $usuario = auth()->user();

        return Derivacion::query()->pendientes()
            ->where('centro_destino_id', $this->centro->id)
            ->with(['historia', 'planOrigen.centro', 'derivadaPor.profesional', 'tipoPlan'])
            ->oldest()
            ->get()
            ->map(function (Derivacion $derivacion) use ($usuario): array {
                $persona = Ciudadano::withoutGlobalScope(AmbitoUoScope::class)->find($derivacion->historia?->ciudadano_id);

                return [
                    'derivacion' => $derivacion,
                    'persona' => $this->nombrePersona($persona, $usuario),
                    'origen' => $derivacion->planOrigen?->centro?->nombre ?? '—',
                    'tsr' => $derivacion->derivadaPor?->nombreVisible() ?? '—',
                    'tipo' => $derivacion->tipoPlan->nombre,
                    'motivo' => $derivacion->motivo,
                    'antiguedad' => $derivacion->created_at->diffForHumans(),
                ];
            })
            ->pipe(fn (Collection $filas) => $this->ordenarFilas($filas));
    }

    /**
     * Columnas ordenables. «Antigüedad» ascendente es de la más reciente a la más antigua.
     *
     * @return array<string, 'asc'|'desc'>
     */
    protected function columnasOrdenables(): array
    {
        return ['persona' => 'asc', 'origen' => 'asc', 'tsr' => 'asc', 'tipo' => 'asc', 'antiguedad' => 'desc'];
    }

    /**
     * Orden inicial: las más antiguas primero.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function ordenInicial(): array
    {
        return ['antiguedad', 'desc'];
    }

    /**
     * Ordena las filas de la bandeja por la columna elegida.
     *
     * @param Collection<int, array<string, mixed>> $filas
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function ordenarFilas(Collection $filas): Collection
    {
        $clave = match ($this->ordenarPor) {
            // Antigüedad = ahora − creación: a más reciente, menor antigüedad.
            'antiguedad' => fn (array $fila) => -$fila['derivacion']->created_at->getTimestamp(),
            default => fn (array $fila) => Str::lower(Str::ascii((string) $fila[$this->ordenarPor])),
        };

        return $filas->sortBy($clave, SORT_REGULAR, $this->direccionSql() === 'desc')->values();
    }

    /**
     * Profesionales de intervención adscritos al centro, posibles responsables del plan.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function profesionales(): Collection
    {
        if ($this->centro === null) {
            return collect();
        }

        return User::query()
            ->whereHas('adscripcionesVigentes', fn ($q) => $q->where('unidad_organizativa_id', $this->centro->unidad_organizativa_id))
            ->with('profesional')
            ->get()
            ->filter(fn (User $usuario) => $usuario->can('plan.editar'))
            ->sortBy(fn (User $usuario) => $usuario->nombreVisible())
            ->values();
    }

    /**
     * Abre la aceptación de una derivación.
     *
     * @param int $derivacionId
     *
     * @return void
     */
    public function prepararAceptacion(int $derivacionId): void
    {
        $this->reset(['rechazarId', 'responsableId', 'aviso']);
        $this->resetErrorBag();
        $this->aceptarId = $derivacionId;
    }

    /**
     * Acepta la derivación con el responsable elegido (el servicio comprueba permisos y adscripción).
     *
     * @return void
     */
    public function aceptar(): void
    {
        $this->validate(['responsableId' => ['required', 'integer']], [], ['responsableId' => 'profesional responsable']);

        try {
            app(DerivacionService::class)->aceptar($this->derivacionDelCentro($this->aceptarId), User::findOrFail($this->responsableId), auth()->user());
        } catch (DomainException $e) {
            $this->addError('responsableId', $e->getMessage());

            return;
        }

        $this->aceptarId = null;
        $this->aviso = 'Derivación aceptada: se ha abierto el plan en borrador con su responsable.';
        unset($this->pendientes);
    }

    /**
     * Abre el rechazo de una derivación.
     *
     * @param int $derivacionId
     *
     * @return void
     */
    public function prepararRechazo(int $derivacionId): void
    {
        $this->reset(['aceptarId', 'motivoRechazo', 'aviso']);
        $this->resetErrorBag();
        $this->rechazarId = $derivacionId;
    }

    /**
     * Rechaza la derivación con motivo.
     *
     * @return void
     */
    public function rechazar(): void
    {
        try {
            app(DerivacionService::class)->rechazar($this->derivacionDelCentro($this->rechazarId), $this->motivoRechazo, auth()->user());
        } catch (DomainException $e) {
            $this->addError('motivoRechazo', $e->getMessage());

            return;
        }

        $this->rechazarId = null;
        $this->aviso = 'Derivación rechazada. Se ha avisado a quien la hizo.';
        unset($this->pendientes);
    }

    /**
     * Cancela la acción en curso.
     *
     * @return void
     */
    public function cancelar(): void
    {
        $this->reset(['aceptarId', 'rechazarId', 'responsableId', 'motivoRechazo']);
        $this->resetErrorBag();
    }

    /**
     * Pinta la bandeja.
     *
     * @return View
     */
    public function render(): View
    {
        return view('supervision::livewire.derivaciones-page');
    }

    /**
     * Recarga la derivación dentro del centro supervisado (nunca una de otro centro por id).
     *
     * @param int|null $id
     *
     * @return Derivacion
     */
    private function derivacionDelCentro(?int $id): Derivacion
    {
        return Derivacion::where('centro_destino_id', $this->centro?->id)->findOrFail($id);
    }

    /**
     * Nombre de la persona en la bandeja: la supervisión adscrita al destino ve siempre la
     * identidad (RN-19); el resto, según la regla general de personas protegidas.
     *
     * @param Ciudadano|null $persona
     * @param User $usuario
     *
     * @return string
     */
    private function nombrePersona(?Ciudadano $persona, User $usuario): string
    {
        if ($persona !== null && $this->resuelve) {
            return $persona->nombre_completo;
        }

        return PresentacionPersona::paraListado($persona, $usuario)['nombre'];
    }
}
